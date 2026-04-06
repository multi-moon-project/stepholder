<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use App\Services\MicrosoftGraphService;

class ExtractLeadsJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    protected $tokenId;

    public function __construct($tokenId)
    {
        $this->tokenId = $tokenId;

        Log::info("[LEADS_JOB::__construct]", [
            'token_id' => $tokenId,
            'queue_connection' => config('queue.default'),
        ]);
    }

    public function handle(MicrosoftGraphService $graph)
    {
        Log::info("[LEADS_JOB] HANDLE START", [
            'token_id' => $this->tokenId,
            'queue' => config('queue.default'),
            'memory' => memory_get_usage(true),
        ]);

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

            Log::info("[LEADS_JOB] EXISTING", compact('before'));

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
                'exists' => !!$nextLink
            ]);

            /*
            ========================================
            📡 FETCH
            ========================================
            */
            $result = $graph->fetchBatch($nextLink, $this->tokenId);

            Log::info("[LEADS_JOB] RAW RESULT", [
                'result_keys' => array_keys($result ?? []),
                'sample' => array_slice($result['data'] ?? [], 0, 1),
            ]);

            $newLeads = $result['data'] ?? [];
            $nextLink = $result['next'] ?? null;

            /*
            ========================================
            🧹 FILTER INVALID EMAIL
            ========================================
            */
            $newLeads = collect($newLeads)
                ->filter(fn($x) => !empty($x['email']))
                ->values()
                ->all();

            Log::info("[LEADS_JOB] FILTERED", [
                'count' => count($newLeads)
            ]);

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

            Log::info("[LEADS_JOB] MERGED", [
                'before' => $before,
                'after' => $after,
                'new' => count($newLeads)
            ]);

            /*
            ========================================
            🔄 STATUS UPDATE
            ========================================
            */
            Cache::put($statusKey, [
                'status' => 'processing',
                'message' => "Extracting ($after leads)",
                'total' => $after
            ], 3600);

            /*
            ========================================
            🚨 STOP
            ========================================
            */
            if ($after === $before && !$nextLink) {

                Log::warning("[LEADS_JOB] STOP NO NEW");

                Cache::forget($nextKey);
                Cache::forget($lockKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'No more leads',
                    'total' => $after
                ], 3600);

                return;
            }

            if (empty($newLeads)) {

                Log::warning("[LEADS_JOB] STOP EMPTY");

                Cache::forget($nextKey);
                Cache::forget($lockKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'Empty API',
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

                Log::info("[LEADS_JOB] CONTINUE NEXT");

                Cache::put($nextKey, $nextLink, 3600);

                // 🔥 FIX: gunakan dispatch() BUKAN self::dispatch
                dispatch(new self($this->tokenId));

                return;
            }

            /*
            ========================================
            ✅ DONE
            ========================================
            */
            Log::info("[LEADS_JOB] DONE FINAL");

            Cache::forget($nextKey);
            Cache::forget($lockKey);

            Cache::put($statusKey, [
                'status' => 'done',
                'message' => 'All leads done',
                'total' => $after
            ], 3600);

        } catch (\Throwable $e) {

            Log::error("[LEADS_JOB] FAILED HARD", [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            Cache::forget($lockKey);

            Cache::put($statusKey, [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 3600);
        }
    }
}