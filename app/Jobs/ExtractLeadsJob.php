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
        $this->tokenId = (string) $tokenId;

        $this->onConnection('redis');
        $this->onQueue('default');

        Log::info("[LEADS_JOB::__construct]", [
            'token_id' => $this->tokenId,
        ]);
    }

    public function handle(MicrosoftGraphService $graph)
    {
        Log::info("[LEADS_JOB] HANDLE START", [
            'token_id' => $this->tokenId,
        ]);

        $key       = 'leads_' . $this->tokenId;
        $statusKey = 'leads_status_' . $this->tokenId;
        $stateKey  = 'graph_next_' . $this->tokenId;
        $lockKey   = 'leads_lock_' . $this->tokenId;
        $runKey    = 'leads_run_' . $this->tokenId;

        try {

            /*
            ===============================
            SAFETY LIMIT (ANTI LOOP)
            ===============================
            */
            $run = Cache::increment($runKey);

            if ($run > 50) {
                Log::warning("[LEADS_JOB] FORCE STOP (LIMIT)");

                Cache::forget($runKey);
                Cache::forget($lockKey);
                Cache::forget($stateKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'Stopped (limit reached)',
                    'total' => count(Cache::get($key, []))
                ], 3600);

                return;
            }

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
            LOAD STATE
            ===============================
            */
            $state = Cache::get($stateKey);

            Log::info("[LEADS_JOB] STATE", [
                'state_exists' => !!$state
            ]);

            /*
            ===============================
            FETCH GRAPH (STATE BASED)
            ===============================
            */
            $result = $graph->fetchBatch($state, $this->tokenId);

            $state = $result['state'] ?? null;

            // 🔥 WAJIB: simpan state
            Cache::put($stateKey, $state, 3600);

            $newLeads = $result['data'] ?? [];

            /*
            ===============================
            FILTER EMAIL VALID
            ===============================
            */
            $newLeads = collect($newLeads)
                ->filter(fn($x) => !empty($x['email']))
                ->values()
                ->all();

            Log::info("[LEADS_JOB] NEW BATCH", [
                'count' => count($newLeads)
            ]);

            /*
            ===============================
            MERGE UNIQUE
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
                'after' => $after
            ]);

            /*
            ===============================
            STOP: NO NEW DATA
            ===============================
            */
            if ($after === $before) {

                Log::warning("[LEADS_JOB] NO NEW DATA → STOP");

                Cache::forget($stateKey);
                Cache::forget($lockKey);
                Cache::forget($runKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'No new unique leads',
                    'total' => $after
                ], 3600);

                return;
            }

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
            STOP: EMPTY DATA
            ===============================
            */
            if (empty($newLeads)) {

                Log::warning("[LEADS_JOB] EMPTY API → STOP");

                Cache::forget($stateKey);
                Cache::forget($lockKey);
                Cache::forget($runKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'No data from API',
                    'total' => $after
                ], 3600);

                return;
            }

            /*
            ===============================
            DONE: ALL FOLDER FINISHED
            ===============================
            */
            if (!$state) {

                Log::info("[LEADS_JOB] DONE ALL FOLDERS");

                Cache::forget($stateKey);
                Cache::forget($lockKey);
                Cache::forget($runKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'All folders processed',
                    'total' => $after
                ], 3600);

                return;
            }

            /*
            ===============================
            CONTINUE NEXT JOB
            ===============================
            */
            Log::info("[LEADS_JOB] CONTINUE NEXT");

            dispatch(new self($this->tokenId))
                ->onConnection('redis')
                ->onQueue('default');

        } catch (\Throwable $e) {

            Log::error("[LEADS_JOB] FAILED", [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            Cache::forget($lockKey);
            Cache::forget($runKey);

            Cache::put($statusKey, [
                'status' => 'failed',
                'message' => $e->getMessage()
            ], 3600);
        }
    }
}