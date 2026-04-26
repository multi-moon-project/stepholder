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
    public $timeout = 300;

    protected $tokenId;

    public function __construct($tokenId)
    {
        $this->tokenId = (string) $tokenId;
        $this->onConnection('redis');
        $this->onQueue('leads');

        Log::info("[LEADS_JOB::__construct]", ['token_id' => $this->tokenId]);
    }

    public function handle(MicrosoftGraphService $graph)
    {
        $trace = 'T' . substr(md5($this->tokenId . microtime()), 0, 8);

        $key = 'leads_' . $this->tokenId;
        $statusKey = 'leads_status_' . $this->tokenId;
        $stateKey = 'graph_next_' . $this->tokenId;
        $lockKey = 'leads_lock_' . $this->tokenId;
        $runKey = 'leads_run_' . $this->tokenId;

        try {

            Log::info("[LEADS][{$trace}] JOB START", [
                'token_id' => $this->tokenId
            ]);

            $run = Cache::increment($runKey);
            $maxRuns = 300;

            Log::info("[LEADS][{$trace}] RUN COUNT", [
                'run' => $run
            ]);

            // 🔥 HARD STOP
            if ($run > $maxRuns) {

                Log::warning("[LEADS][{$trace}] FORCE STOP (RUN LIMIT)");

                Cache::forget($runKey);
                Cache::forget($lockKey);
                Cache::forget($stateKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'Stopped safely (run limit)',
                    'total' => count(Cache::get($key, []))
                ], 3600);

                return;
            }

            // 🔥 LOAD EXISTING
            $existing = Cache::get($key, []);
            $before = count($existing);

            Log::info("[LEADS][{$trace}] EXISTING DATA", [
                'count' => $before
            ]);

            Cache::put($statusKey, [
                'status' => 'processing',
                'message' => "Fetching batch...",
                'total' => $before
            ], 3600);

            // 🔥 LOAD STATE
            $state = Cache::get($stateKey);

            Log::info("[LEADS][{$trace}] LOAD STATE", [
                'has_state' => (bool) $state
            ]);

            // 🔥 FETCH BATCH (TRACE SYNC)
            $result = $graph->fetchBatch($state, $this->tokenId, $trace);

            $state = $result['state'] ?? null;

            // 🔥 SAVE STATE
            if ($state) {
                Cache::put($stateKey, $state, 3600);

                Log::info("[LEADS][{$trace}] SAVE STATE", [
                    'folder_index' => $state['folder_index'] ?? null,
                    'page' => $state['page'] ?? null
                ]);
            } else {
                Cache::forget($stateKey);

                Log::info("[LEADS][{$trace}] STATE FINISHED");
            }

            // 🔥 FILTER DATA
            $newLeads = collect($result['data'] ?? [])
                ->filter(fn($x) => !empty($x['email']))
                ->unique('email')
                ->values()
                ->all();

            Log::info("[LEADS][{$trace}] NEW LEADS", [
                'count' => count($newLeads)
            ]);

            // 🔥 MERGE
            $merged = collect($existing)
                ->merge($newLeads)
                ->unique('email')
                ->sortBy('email')
                ->values()
                ->all();

            $after = count($merged);

            Cache::put($key, $merged, 3600);

            Log::info("[LEADS][{$trace}] MERGED", [
                'before' => $before,
                'added' => count($newLeads),
                'after' => $after
            ]);

            // 🔥 DONE
            if (!$state) {

                Log::info("[LEADS][{$trace}] DONE ALL");

                Cache::forget($lockKey);
                Cache::forget($runKey);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => "Extraction complete",
                    'total' => $after
                ], 3600);

                return;
            }

            // 🔥 CONTINUE LOOP
            Cache::put($statusKey, [
                'status' => 'processing',
                'message' => "Extracting ({$after} leads)",
                'total' => $after
            ], 3600);

            Log::info("[LEADS][{$trace}] DISPATCH NEXT");

            dispatch(new self($this->tokenId))
                ->onConnection('redis')
                ->onQueue('leads');

        } catch (\Throwable $e) {

            Log::error("[LEADS][{$trace}] FAILED", [
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