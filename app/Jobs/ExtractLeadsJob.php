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
            📊 LOAD EXISTING
            ========================================
            */
            $existing = Cache::get($key, []);
            $before   = count($existing);

            /*
            ========================================
            🔄 STATUS: PROCESSING (WAJIB ADA TOTAL)
            ========================================
            */
            Cache::put($statusKey, [
                'status' => 'processing',
                'message' => "Fetching batch...",
                'total' => $before
            ], 3600);

            /*
            ========================================
            📥 LOAD NEXT LINK
            ========================================
            */
            $nextLink = Cache::get($nextKey);

            /*
            ========================================
            📡 FETCH BATCH
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
            $merged = collect($existing)
                ->merge($newLeads)
                ->unique('email')
                ->values()
                ->all();

            $after = count($merged);

            Cache::put($key, $merged, 3600);

            Log::info("Leads [{$this->tokenId}] before: $before | after: $after | new: " . count($newLeads));

            /*
            ========================================
            🔄 UPDATE PROGRESS (REALTIME SSE)
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

            // ❌ Tidak ada data & tidak ada next
            if ($after === $before && !$nextLink) {

                Log::warning("STOP [{$this->tokenId}]: No new leads");

                Cache::forget($nextKey);
                Cache::forget($lockKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'No more new unique leads',
                    'total' => $after
                ], 3600);

                return;
            }

            // ❌ Batch kosong
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
            🔁 CONTINUE (AUTO LOOP)
            ========================================
            */
            if ($nextLink) {

                Cache::put($nextKey, $nextLink, 3600);

                // 🔥 tetap processing (bukan continue)
                Cache::put($statusKey, [
                    'status' => 'processing',
                    'message' => "Next batch... ($after leads)",
                    'total' => $after
                ], 3600);

                usleep(500000); // anti rate limit

                self::dispatch($this->tokenId);

                return;
            }

            /*
            ========================================
            ✅ DONE
            ========================================
            */
            Log::info("DONE [{$this->tokenId}]");

            Cache::forget($nextKey);
            Cache::forget($lockKey);

            Cache::put($statusKey, [
                'status' => 'done',
                'message' => 'All leads extracted',
                'total' => $after
            ], 3600);

        } catch (\Throwable $e) {

            Log::error("ExtractLeadsJob FAILED [{$this->tokenId}]: " . $e->getMessage());

            Cache::forget($lockKey);

            Cache::put($statusKey, [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'total' => count(Cache::get($key, []))
            ], 3600);
        }
    }
}