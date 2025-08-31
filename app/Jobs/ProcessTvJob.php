<?php

declare(strict_types=1);

/**
 * NOTICE OF LICENSE.
 *
 * UNIT3D Community Edition is open-sourced software licensed under the GNU Affero General Public License v3.0
 * The details is bundled with this project in the file LICENSE.txt.
 *
 * @project    UNIT3D Community Edition
 *
 * @author     HDVinnie <hdinnovations@protonmail.com>
 * @license    https://www.gnu.org/licenses/agpl-3.0.en.html/ GNU Affero General Public License v3.0
 */

namespace App\Jobs;

use App\Enums\GlobalRateLimit;
use App\Models\TmdbCompany;
use App\Models\TmdbCredit;
use App\Models\TmdbGenre;
use App\Models\TmdbNetwork;
use App\Models\TmdbPerson;
use App\Models\Torrent;
use App\Models\TmdbTv;
use App\Services\Tmdb\Client;
use DateTime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\Skip;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ProcessTvJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * ProcessTvJob Constructor.
     */
    public function __construct(public int $id)
    {
    }

    /**
     * The number of seconds the job can run before timing out.
     *
     * Some shows have 2000+ credits requiring more than the default of 60 seconds.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            Skip::when(cache()->has("tmdb-tv-scraper:{$this->id}")),
            new WithoutOverlapping((string) $this->id)->dontRelease()->expireAfter(30),
            new RateLimited(GlobalRateLimit::TMDB),
        ];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): DateTime
    {
        return now()->addDay();
    }

    public function handle(): void
    {
        try {
            // Tv
            $tvScraper = new Client\TV($this->id);
            \Log::debug('ProcessTvJob started', ['tv_id' => $this->id]);

            if ($tvScraper->getTv() === null) {
                \Log::error('TMDB API returned null for TV ID', ['tv_id' => $this->id, 'scraper_data' => $tvScraper->data ?? null]);
                return;
            }
            \Log::debug('TMDB API returned TV data', ['tv_id' => $this->id, 'tv_data' => $tvScraper->getTv()]);

            $tv = TmdbTv::updateOrCreate(['id' => $this->id], $tvScraper->getTv());
            \Log::debug('Updated or created TmdbTv', ['tv_id' => $this->id, 'tv_model' => $tv]);

            // Companies
            $companies = [];
            \Log::debug('Processing production companies', ['tv_id' => $this->id, 'companies_raw' => $tvScraper->data['production_companies'] ?? null]);

            foreach ($tvScraper->data['production_companies'] ?? [] as $company) {
                $companies[] = (new Client\Company($company['id']))->getCompany();
                \Log::debug('Fetched company', ['company_id' => $company['id'], 'company_data' => end($companies)]);
            }

            TmdbCompany::upsert($companies, 'id');
            $tv->companies()->sync(array_unique(array_column($companies, 'id')));
            \Log::debug('Upserted and synced companies', ['tv_id' => $this->id, 'company_ids' => array_column($companies, 'id')]);

            // Networks
            $networks = [];
            \Log::debug('Processing networks', ['tv_id' => $this->id, 'networks_raw' => $tvScraper->data['networks'] ?? null]);

            foreach ($tvScraper->data['networks'] ?? [] as $network) {
                $networks[] = (new Client\Network($network['id']))->getNetwork();
                \Log::debug('Fetched network', ['network_id' => $network['id'], 'network_data' => end($networks)]);
            }

            TmdbNetwork::upsert($networks, 'id');
            $tv->networks()->sync(array_unique(array_column($networks, 'id')));
            \Log::debug('Upserted and synced networks', ['tv_id' => $this->id, 'network_ids' => array_column($networks, 'id')]);

            // Genres
            TmdbGenre::upsert($tvScraper->getGenres(), 'id');
            $tv->genres()->sync(array_unique(array_column($tvScraper->getGenres(), 'id')));
            \Log::debug('Upserted and synced genres', ['tv_id' => $this->id, 'genre_ids' => array_column($tvScraper->getGenres(), 'id')]);

            // People
            $credits = $tvScraper->getCredits();
            $people = [];
            $cache = [];
            \Log::debug('Processing credits', ['tv_id' => $this->id, 'credits' => $credits]);

            foreach (array_unique(array_column($credits, 'tmdb_person_id')) as $personId) {
                // TMDB caches their api responses for 8 hours, so don't abuse them
                $cacheKey = "tmdb-person-scraper:{$personId}";
                if (cache()->has($cacheKey)) {
                    \Log::debug('Person cache hit, skipping', ['person_id' => $personId]);
                    continue;
                }
                $people[] = (new Client\Person($personId))->getPerson();
                \Log::debug('Fetched person', ['person_id' => $personId, 'person_data' => end($people)]);
                $cache[$cacheKey] = now();
            }

            foreach (collect($people)->chunk(intdiv(65_000, 13)) as $people) {
                TmdbPerson::upsert($people->toArray(), 'id');
                \Log::debug('Upserted people chunk', ['tv_id' => $this->id, 'chunk_size' => count($people)]);
            }

            if ($cache !== []) {
                cache()->put($cache, 8 * 3600);
                \Log::debug('Updated person cache', ['tv_id' => $this->id, 'cache_keys' => array_keys($cache)]);
            }

            TmdbCredit::where('tmdb_tv_id', '=', $this->id)->delete();
            TmdbCredit::upsert($credits, ['tmdb_person_id', 'tmdb_movie_id', 'tmdb_tv_id', 'occupation_id', 'character']);
            \Log::debug('Upserted credits', ['tv_id' => $this->id, 'credit_count' => count($credits)]);

            // Recommendations
            $tv->recommendedTv()->sync(array_unique(array_column($tvScraper->getRecommendations(), 'recommended_tmdb_tv_id')));
            \Log::debug('Synced recommended TV', ['tv_id' => $this->id, 'recommendations' => $tvScraper->getRecommendations()]);

            Torrent::query()
                ->where('tmdb_tv_id', '=', $this->id)
                ->whereRelation('category', 'tv_meta', '=', true)
                ->searchable();
            \Log::debug('Marked torrents as searchable', ['tv_id' => $this->id]);

            // TMDB caches their api responses for 8 hours, so don't abuse them
            cache()->put("tmdb-tv-scraper:{$this->id}", now(), 8 * 3600);
            \Log::debug('Updated TV scraper cache', ['tv_id' => $this->id]);
        } catch (\Throwable $e) {
            \Log::error('ProcessTvJob failed with exception', [
                'tv_id' => $this->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed($exception): void
    {
        \Log::error('ProcessTvJob permanently failed', [
            'tv_id' => $this->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
