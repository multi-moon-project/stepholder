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

    // 🔥 FORCE REDIS
    public $connection = 'redis';
    public $queue = 'default';

    public $tries = 3;
    public $timeout = 120;

    protected $tokenId;

    public function __construct($tokenId)
    {
        // 🔥 pastikan primitive
        $this->tokenId = (string) $tokenId;

        Log::info("[LEADS_JOB::__construct]", [
            'token_id' => $this->tokenId,
            'env_queue' => env('QUEUE_CONNECTION'),
            'config_queue' => config('queue.default')
        ]);
    }

    public function handle(MicrosoftGraphService $graph)
    {
        Log::info("[LEADS_JOB] HANDLE START", [
            'token_id' => $this->tokenId,
            'connection' => $this->connection,
            'queue' => $this->queue
        ]);

        $key       = 'leads_' . $this->tokenId;
        $statusKey = 'leads_status_' . $this->tokenId;
        $nextKey   = 'graph_next_' . $this->tokenId;
        $lockKey   = 'leads_lock_' . $this->tokenId;

        try {

            /*
            ===============================
            LOAD EXISTING
            ===============================
            */
            $existing = Cache::get($key, []);
            $before   = count($existing);

            Log::info("[LEADS_JOB] EXISTING", compact('before'));

            Cache::put($statusKey, [
                'status' => 'processing',
                'message' => "Fetching batch...",
                'total' => $before
            ], 3600);

            /*
            ===============================
            NEXT LINK
            ===============================
            */
            $nextLink = Cache::get($nextKey);

            Log::info("[LEADS_JOB] NEXT LINK", [
                'exists' => !!$nextLink
            ]);

            /*
            ===============================
            FETCH GRAPH
            ===============================
            */
            $result = $graph->fetchBatch($nextLink, $this->tokenId);

            Log::info("[LEADS_JOB] RAW RESULT", [
                'keys' => array_keys($result ?? []),
                'sample' => array_slice($result['data'] ?? [], 0, 1)
            ]);

            $newLeads = $result['data'] ?? [];
            $nextLink = $result['next'] ?? null;

            /*
            ===============================
            FILTER EMAIL VALID
            ===============================
            */
            $newLeads = collect($newLeads)
                ->filter(fn($x) => !empty($x['email']))
                ->values()
                ->all();

            Log::info("[LEADS_JOB] FILTERED", [
                'count' => count($newLeads)
            ]);

            /*
            ===============================
            MERGE
            ===============================
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
            ===============================
            UPDATE STATUS
            ===============================
            */
            Cache::put($statusKey, [
                'status' => 'processing',
                'message' => "Extracting ($after leads)",
                'total' => $after
            ], 3600);

            /*
            ===============================
            STOP CONDITION
            ===============================
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
                    'message' => 'No data from API',
                    'total' => $after
                ], 3600);

                return;
            }

            /*
            ===============================
            CONTINUE NEXT PAGE
            ===============================
            */
            if ($nextLink) {

                Log::info("[LEADS_JOB] CONTINUE NEXT");

                Cache::put($nextKey, $nextLink, 3600);

                // 🔥 FIX PENTING (JANGAN self::dispatch)
                dispatch(new self($this->tokenId))
                    ->onConnection('redis')
                    ->onQueue('default');

                return;
            }

            /*
            ===============================
            DONE
            ===============================
            */
            Log::info("[LEADS_JOB] DONE FINAL");

            Cache::forget($nextKey);
            Cache::forget($lockKey);

            Cache::put($statusKey, [
                'status' => 'done',
                'message' => 'All leads extracted',
                'total' => $after
            ], 3600);

        } catch (\Throwable $e) {

            Log::error("[LEADS_JOB] FAILED", [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            Cache::forget($lockKey);

            Cache::put($statusKey, [
                'status' => 'failed',
                'message' => $e->getMessage()
            ], 3600);
        }
    }
}