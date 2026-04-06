<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\MicrosoftGraphService;

class ExtractLeadsJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    protected $tokenId;

    public function __construct($tokenId)
    {
        $this->tokenId = $tokenId;
    }

    public function handle(MicrosoftGraphService $graph)
    {
        $key       = 'leads_' . $this->tokenId;
        $statusKey = 'leads_status_' . $this->tokenId;
        $nextKey   = 'graph_next_' . $this->tokenId;
        $lockKey   = 'leads_lock_' . $this->tokenId;

        try {

            Log::info("[LEADS_JOB] START", [
                'token_id' => $this->tokenId
            ]);

            /*
            ========================================
            📊 LOAD EXISTING
            ========================================
            */
            $existing = Cache::get($key, []);
            $before   = count($existing);

            Log::info("[LEADS_JOB] EXISTING", [
                'count' => $before
            ]);

            /*
            ========================================
            🔄 STATUS
            ========================================
            */
            Cache::put($statusKey, [
                'status' => 'processing',
                'message' => "Fetching batch...",
                'total' => $before
            ], 3600);

            /*
            ========================================
            📥 NEXT LINK
            ========================================
            */
            $nextLink = Cache::get($nextKey);

            Log::info("[LEADS_JOB] NEXT LINK", [
                'next' => $nextLink ? 'YES' : 'NO'
            ]);

            /*
            ========================================
            📡 FETCH BATCH
            ========================================
            */
            try {

                $result = $graph->fetchBatch($nextLink, $this->tokenId);

            } catch (\Throwable $e) {

                Log::error("[LEADS_JOB] FETCH ERROR", [
                    'error' => $e->getMessage(),
                    'token_id' => $this->tokenId
                ]);

                throw $e;
            }

            Log::info("[LEADS_JOB] FETCH RESULT", [
                'has_data' => isset($result['data']),
                'count' => count($result['data'] ?? []),
                'has_next' => !empty($result['next'])
            ]);

            $newLeads = $result['data'] ?? [];
            $nextLink = $result['next'] ?? null;

            /*
            ========================================
            📦 MERGE
            ========================================
            */
            $merged = collect($existing)
                ->merge($newLeads)
                ->unique('email')
                ->values()
                ->all();

            $after = count($merged);

            Cache::put($key, $merged, 3600);

            Log::info("[LEADS_JOB] MERGE", [
                'before' => $before,
                'after' => $after,
                'new' => count($newLeads)
            ]);

            /*
            ========================================
            🔄 UPDATE PROGRESS
            ========================================
            */
            Cache::put($statusKey, [
                'status' => 'processing',
                'message' => "Extracting... ($after leads)",
                'total' => $after
            ], 3600);

            /*
            ========================================
            🚨 STOP CONDITIONS
            ========================================
            */

            if ($after === $before && !$nextLink) {

                Log::warning("[LEADS_JOB] STOP NO NEW", [
                    'token_id' => $this->tokenId
                ]);

                Cache::forget($nextKey);
                Cache::forget($lockKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'No more new leads',
                    'total' => $after
                ], 3600);

                return;
            }

            if (empty($newLeads)) {

                Log::warning("[LEADS_JOB] STOP EMPTY", [
                    'token_id' => $this->tokenId
                ]);

                Cache::forget($nextKey);
                Cache::forget($lockKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'No data from API',
                    'total' => $after
                ], 3600);

                return;
            }

            /*
            ========================================
            🔁 CONTINUE
            ========================================
            */
            if ($nextLink) {

                Log::info("[LEADS_JOB] CONTINUE", [
                    'next_exists' => true,
                    'token_id' => $this->tokenId
                ]);

                Cache::put($nextKey, $nextLink, 3600);

                Cache::put($statusKey, [
                    'status' => 'processing',
                    'message' => "Next batch... ($after leads)",
                    'total' => $after
                ], 3600);

                usleep(500000);

                self::dispatch($this->tokenId);

                return;
            }

            /*
            ========================================
            ✅ DONE
            ========================================
            */
            Log::info("[LEADS_JOB] DONE", [
                'total' => $after
            ]);

            Cache::forget($nextKey);
            Cache::forget($lockKey);

            Cache::put($statusKey, [
                'status' => 'done',
                'message' => 'All leads extracted',
                'total' => $after
            ], 3600);

        } catch (\Throwable $e) {

            Log::error("[LEADS_JOB] FAILED", [
                'token_id' => $this->tokenId,
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500)
            ]);

            Cache::forget($lockKey);

            Cache::put($statusKey, [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'total' => count(Cache::get($key, []))
            ], 3600);
        }
    }
}