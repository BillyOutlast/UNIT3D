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

    public string $id;
    public string $type;

    /**
     * Create a new job instance.
     */
    public function __construct(string $id, string $type)
    {
        $this->id = $id;
        $this->type = $type; // 'scenes', 'movies', or 'jave'
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $endpoint = sprintf('https://api.theporndb.net/%s/%s?add_to_collection=false', $this->type, $this->id);
        $apiKey = config('api-keys.theporndb');
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
        ];
        Log::debug('ThePornDBVideoScraper: Request', [
            'endpoint' => $endpoint,
            'headers' => $headers,
        ]);
        $response = Http::withHeaders($headers)->get($endpoint);
        Log::debug('ThePornDBVideoScraper: Response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $main = $data['data'] ?? [];
            $performers = [];
            foreach (($main['performers'] ?? []) as $p) {
                if (isset($p['id']) && isset($p['name'])) {
                    $performers[] = [
                        'id' => $p['id'],
                        'name' => $p['name'],
                    ];
                }
            }
            $site = isset($main['site']) ? [
                'uuid' => $main['site']['uuid'] ?? null,
                'name' => $main['site']['name'] ?? null,
            ] : null;
            $tags = [];
            foreach (($main['tags'] ?? []) as $t) {
                $tags[] = [
                    'uuid' => $t['uuid'] ?? null,
                    'name' => $t['name'] ?? null,
                ];
            }
            $save = [
                'theporndb_scene_id' => $main['id'] ?? null,
                'title' => $main['title'] ?? null,
                'type' => $main['type'] ?? null,
                'url' => $main['url'] ?? null,
                'image' => $main['image'] ?? null,
                'description' => $main['description'] ?? null,
                'performers' => json_encode($performers),
                'site' => json_encode($site),
                'tags' => json_encode($tags),
                'raw' => json_encode($data),
            ];
            if ($this->type === 'scenes') {
                \App\Models\ThePornDbSceneMeta::updateOrCreate([
                    'theporndb_scene_id' => $main['id'] ?? $this->id,
                ], $save);
            } elseif ($this->type === 'movies') {
                \App\Models\PornMovieMeta::updateOrCreate([
                    'theporndb_movie_id' => $main['id'] ?? $this->id,
                ], $save);
            } elseif ($this->type === 'jave') {
                \App\Models\PornJavMeta::updateOrCreate([
                    'theporndb_jav_id' => $main['id'] ?? $this->id,
                ], $save);
            }
        } else {
            Log::error('ThePornDBVideoScraper failed', ['id' => $this->id, 'type' => $this->type, 'response' => $response->body()]);
        }
    }
}
