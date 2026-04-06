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

            /*
            ========================================
            🔄 STATUS: PROCESSING
            ========================================
            */
            Cache::put($statusKey, [
                'status' => 'processing',
                'message' => 'Fetching batch...',
            ], 3600);

            /*
            ========================================
            📥 LOAD NEXT LINK
            ========================================
            */
            $nextLink = Cache::get($nextKey);

            /*
            ========================================
            📡 FETCH BATCH (🔥 FIX: PASS TOKEN ID)
            ========================================
            */
            $result = $graph->fetchBatch($nextLink, $this->tokenId);

            $newLeads = $result['data'] ?? [];
            $nextLink = $result['next'] ?? null;

            /*
            ========================================
            📦 MERGE + UNIQUE
            ========================================
            */
            $existing = Cache::get($key, []);

            $before = count($existing);

            $merged = collect($existing)
                ->merge($newLeads)
                ->unique('email')
                ->values()
                ->all();

            $after = count($merged);

            Cache::put($key, $merged, 3600);

            Log::info("Leads [token {$this->tokenId}] before: $before | after: $after | new: " . count($newLeads));

            /*
            ========================================
            🚨 STOP CONDITIONS
            ========================================
            */

            // ❌ 1. Tidak ada data baru & tidak ada nextLink
            if ($after === $before && !$nextLink) {

                Log::warning("STOP [{$this->tokenId}]: No new leads (END)");

                Cache::forget($nextKey);
                Cache::forget($lockKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'No more new unique leads',
                    'total' => $after
                ], 3600);

                return;
            }

            // ❌ 2. Batch kosong
            if (empty($newLeads)) {

                Log::warning("STOP [{$this->tokenId}]: Empty batch");

                Cache::forget($nextKey);
                Cache::forget($lockKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'No more data from API',
                    'total' => $after
                ], 3600);

                return;
            }

            /*
            ========================================
            🔁 CONTINUE OR FINISH
            ========================================
            */

            if ($nextLink) {

                Cache::put($nextKey, $nextLink, 3600);

                Cache::put($statusKey, [
                    'status' => 'continue',
                    'message' => 'Next batch...',
                    'total' => $after
                ], 3600);

                // 🔥 Anti rate limit
                usleep(500000); // 0.5 detik

                self::dispatch($this->tokenId);

            } else {

                Log::info("DONE [{$this->tokenId}]: No nextLink");

                Cache::forget($nextKey);
                Cache::forget($lockKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'All leads extracted',
                    'total' => $after
                ], 3600);
            }

        } catch (\Throwable $e) {

            Log::error("ExtractLeadsJob FAILED [{$this->tokenId}]: " . $e->getMessage());

            Cache::forget($lockKey);

            Cache::put($statusKey, [
                'status' => 'failed',
                'message' => $e->getMessage()
            ], 3600);
        }
    }
}