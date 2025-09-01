<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ThePornDBVideoScraper implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $torrentId;
    public string $id;
    public string $type;

    /**
     * Create a new job instance.
     */
    public function __construct(string $id, string $type, int $torrentId)
    {
        $this->id = $id;
        $this->type = $type; // 'scenes', 'movies', or 'jave'
        $this->torrentId = $torrentId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $endpoint = sprintf('https://api.theporndb.net/%s/%s?add_to_collection=false', $this->type, $this->id);
        $response = Http::get($endpoint);

        if ($response->successful()) {
            $data = $response->json();
            if ($this->type === 'scenes') {
                \App\Models\PornSceneMeta::updateOrCreate([
                    'scene_id' => $this->id,
                    'torrent_id' => $this->torrentId,
                ], [
                    'title' => $data['title'] ?? null,
                    'release_date' => $data['release_date'] ?? null,
                    'studio' => $data['studio'] ?? null,
                    'performers' => $data['performers'] ?? null,
                    'urls' => $data['urls'] ?? null,
                    'details' => $data['details'] ?? null,
                    'director' => $data['director'] ?? null,
                    'raw' => $data,
                ]);
            } elseif ($this->type === 'movies') {
                \App\Models\PornMovieMeta::updateOrCreate([
                    'movie_id' => $this->id,
                    'torrent_id' => $this->torrentId,
                ], [
                    'title' => $data['title'] ?? null,
                    'release_date' => $data['release_date'] ?? null,
                    'studio' => $data['studio'] ?? null,
                    'performers' => $data['performers'] ?? null,
                    'urls' => $data['urls'] ?? null,
                    'details' => $data['details'] ?? null,
                    'director' => $data['director'] ?? null,
                    'raw' => $data,
                ]);
            } elseif ($this->type === 'jave') {
                \App\Models\PornJavMeta::updateOrCreate([
                    'jav_id' => $this->id,
                    'torrent_id' => $this->torrentId,
                ], [
                    'title' => $data['title'] ?? null,
                    'release_date' => $data['release_date'] ?? null,
                    'studio' => $data['studio'] ?? null,
                    'performers' => $data['performers'] ?? null,
                    'urls' => $data['urls'] ?? null,
                    'details' => $data['details'] ?? null,
                    'director' => $data['director'] ?? null,
                    'raw' => $data,
                ]);
            }
        } else {
            Log::error('ThePornDBVideoScraper failed', ['id' => $this->id, 'type' => $this->type, 'response' => $response->body()]);
        }
    }
}
