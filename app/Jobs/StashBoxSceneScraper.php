<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class StashBoxSceneScraper implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $sceneId;
    public string $endpoint;

    /**
     * Create a new job instance.
     */
    public function __construct(string $sceneId, string $endpoint)
    {
        $this->sceneId = $sceneId;
        $this->endpoint = $endpoint;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $query = <<<'GRAPHQL'
        query MyQuery {
          findScene(id: "%s") {
            id
            title
            release_date
            studio { id name }
            performers { performer { id name disambiguation } }
            urls { type url }
            details
            director
          }
        }
        GRAPHQL;

        $graphqlQuery = sprintf($query, $this->sceneId);
        $response = Http::post($this->endpoint, [
            'query' => $graphqlQuery,
        ]);

        if ($response->successful()) {
            $scene = $response->json()['data']['findScene'] ?? [];
            if ($this->endpoint === 'https://stashdb.org/graphql') {
                \App\Models\StashdbMeta::updateOrCreate([
                    'stashdb_id' => $this->sceneId,
                ], [
                    'raw' => $scene,
                ]);
            } elseif ($this->endpoint === 'https://fansdb.cc/graphql') {
                \App\Models\FansdbMeta::updateOrCreate([
                    'fansdb_id' => $this->sceneId,
                ], [
                    'raw' => $scene,
                ]);
            }
        }
    }
}
