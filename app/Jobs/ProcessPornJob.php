<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Torrent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\Skip;
use DateTime;

class ProcessPornJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180;
    public $failOnTimeout = true;

    public function __construct(public int $torrentId) {}

    public function middleware(): array
    {
        return [
            Skip::when(cache()->has("porn-meta-job:{$this->torrentId}")),
            new WithoutOverlapping((string) $this->torrentId)->dontRelease()->expireAfter(30),
            new RateLimited('porn-meta'),
        ];
    }

    public function retryUntil(): DateTime
    {
        return now()->addDay();
    }

    public function handle(): void
    {
        $torrent = Torrent::find($this->torrentId);
        \Log::debug('ProcessPornJob: Torrent debug data', [
            'torrent_id' => $this->torrentId,
            'torrent' => $torrent,
        ]);
        if (! $torrent || $torrent->category_id !== 3) {
            \Log::error('ProcessPornJob: Torrent not found or not category_id 3', ['torrent_id' => $this->torrentId]);
            return;
        }

        // Example: Gather metadata for each ID
        $pornMeta = [
            'theporndb_scene_id' => $torrent->theporndb_scene_id,
            'theporndb_movie_id' => $torrent->theporndb_movie_id,
            'theporndb_jav_id'   => $torrent->theporndb_jav_id,
            'stashdb_id'         => $torrent->stashdb_id,
            'fansdb_id'          => $torrent->fansdb_id,
        ];

        foreach ($pornMeta as $key => $id) {
            if ($id) {
                // Example: Call external API for $key/$id and store metadata
                \Log::info('ProcessPornJob: Fetching metadata', ['key' => $key, 'id' => $id]);
                $metadata = null;
                if ($key === 'theporndb_scene_id') {
                    dispatch(new \App\Jobs\ThePornDBVideoScraper($id, 'scenes', $torrent->id));
                    // $metadata = fetchThePornDbScene($id); // Your scraper logic
                    \App\Models\ThePornDbSceneMeta::updateOrCreate([
                        'theporndb_scene_id' => $id,
                        'torrent_id' => $torrent->id,
                    ], [
                        'raw' => $metadata,
                    ]);
                } elseif ($key === 'theporndb_movie_id') {
                    dispatch(new \App\Jobs\ThePornDBVideoScraper($id, 'movies', $torrent->id));
                    // $metadata = fetchThePornDbMovie($id);
                    \App\Models\PornMovieMeta::updateOrCreate([
                        'movie_id' => $id,
                        'torrent_id' => $torrent->id,
                    ], [
                        'title' => $metadata['title'] ?? null,
                        'release_date' => $metadata['release_date'] ?? null,
                        'studio' => $metadata['studio'] ?? null,
                        'performers' => $metadata['performers'] ?? null,
                        'urls' => $metadata['urls'] ?? null,
                        'details' => $metadata['details'] ?? null,
                        'director' => $metadata['director'] ?? null,
                        'raw' => $metadata,
                    ]);
                } elseif ($key === 'theporndb_jav_id') {
                    dispatch(new \App\Jobs\ThePornDBVideoScraper($id, 'jave', $torrent->id));
                    // $metadata = fetchThePornDbJav($id);
                    \App\Models\PornJavMeta::updateOrCreate([
                        'jav_id' => $id,
                        'torrent_id' => $torrent->id,
                    ], [
                        'title' => $metadata['title'] ?? null,
                        'release_date' => $metadata['release_date'] ?? null,
                        'studio' => $metadata['studio'] ?? null,
                        'performers' => $metadata['performers'] ?? null,
                        'urls' => $metadata['urls'] ?? null,
                        'details' => $metadata['details'] ?? null,
                        'director' => $metadata['director'] ?? null,
                        'raw' => $metadata,
                    ]);
                } elseif ($key === 'stashdb_id') {
                    $endpoint = 'https://stashdb.org/graphql';
                    dispatch(new \App\Jobs\StashBoxSceneScraper($id, $endpoint, $torrent->id));
                } elseif ($key === 'fansdb_id') {
                    $endpoint = 'https://fansdb.cc/graphql';
                    dispatch(new \App\Jobs\StashBoxSceneScraper($id, $endpoint, $torrent->id));
                }
            }
        }

        cache()->put("porn-meta-job:{$this->torrentId}", now(), 8 * 3600);
        \Log::info('ProcessPornJob: Completed', ['torrent_id' => $this->torrentId]);
    }

    public function failed($exception): void
    {
        \Log::error('ProcessPornJob permanently failed', [
            'torrent_id' => $this->torrentId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
