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

        // Gather metadata for each external ID and dispatch scrapers
        if ($torrent->theporndb_scene_id) {
            \Log::info('ProcessPornJob: Fetching ThePornDB Scene metadata', ['id' => $torrent->theporndb_scene_id]);
            dispatch(new \App\Jobs\ThePornDBVideoScraper($torrent->theporndb_scene_id, 'scenes'));
        }
        if ($torrent->theporndb_movie_id) {
            \Log::info('ProcessPornJob: Fetching ThePornDB Movie metadata', ['id' => $torrent->theporndb_movie_id]);
            dispatch(new \App\Jobs\ThePornDBVideoScraper($torrent->theporndb_movie_id, 'movies'));
        }
        if ($torrent->theporndb_jav_id) {
            \Log::info('ProcessPornJob: Fetching ThePornDB JAV metadata', ['id' => $torrent->theporndb_jav_id]);
            dispatch(new \App\Jobs\ThePornDBVideoScraper($torrent->theporndb_jav_id, 'jave'));
        }
        if ($torrent->stashdb_id) {
            \Log::info('ProcessPornJob: Fetching StashDB metadata', ['id' => $torrent->stashdb_id]);
            $endpoint = 'https://stashdb.org/graphql';
            dispatch(new \App\Jobs\StashBoxSceneScraper($torrent->stashdb_id, $endpoint));
        }
        if ($torrent->fansdb_id) {
            \Log::info('ProcessPornJob: Fetching FansDB metadata', ['id' => $torrent->fansdb_id]);
            $endpoint = 'https://fansdb.cc/graphql';
            dispatch(new \App\Jobs\StashBoxSceneScraper($torrent->fansdb_id, $endpoint));
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
