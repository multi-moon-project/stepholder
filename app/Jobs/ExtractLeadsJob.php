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
        $this->onQueue('default');

        Log::info("[LEADS_JOB::__construct]", ['token_id' => $this->tokenId]);
    }

   public function handle(MicrosoftGraphService $graph)
{
    $key       = 'leads_' . $this->tokenId;
    $statusKey = 'leads_status_' . $this->tokenId;
    $stateKey  = 'graph_next_' . $this->tokenId;
    $lockKey   = 'leads_lock_' . $this->tokenId;
    $runKey    = 'leads_run_' . $this->tokenId;

    try {
        Log::info("[LEADS_JOB] HANDLE START", ['token_id' => $this->tokenId]);

        $run = Cache::increment($runKey);
        $maxRuns = 300;

        if ($run > $maxRuns) {
            Log::warning("[LEADS_JOB] FORCE STOP (RUN LIMIT)", [
                'run' => $run,
                'max' => $maxRuns
            ]);

            Cache::forget($runKey);
            Cache::forget($lockKey);
            Cache::forget($stateKey);

            Cache::put($statusKey, [
                'status' => 'done',
                'message' => 'Stopped safely (run limit reached)',
                'total' => count(Cache::get($key, []))
            ], 3600);

            return;
        }

        $existing = Cache::get($key, []);
        $before   = count($existing);

        Cache::put($statusKey, [
            'status' => 'processing',
            'message' => "Fetching batch...",
            'total' => $before
        ], 3600);

        $state = Cache::get($stateKey);

        $result = $graph->fetchBatch($state, $this->tokenId);
        $state  = $result['state'] ?? null;

        if ($state) {
            Cache::put($stateKey, $state, 3600);
        } else {
            Cache::forget($stateKey);
        }

        $newLeads = collect($result['data'] ?? [])
            ->filter(fn($x) => !empty($x['email']))
            ->unique('email')
            ->values()
            ->all();

        $merged = collect($existing)
            ->merge($newLeads)
            ->unique('email')
            ->sortBy('email')
            ->values()
            ->all();

        $after = count($merged);

        Cache::put($key, $merged, 3600);

        Log::info("[LEADS_JOB] BATCH RESULT", [
            'before' => $before,
            'batch_count' => count($newLeads),
            'after' => $after,
            'has_state' => (bool) $state
        ]);

        // selesai hanya jika memang state habis
        if (!$state) {
            Cache::forget($lockKey);
            Cache::forget($runKey);

            Cache::put($statusKey, [
                'status' => 'done',
                'message' => "Extraction complete",
                'total' => $after
            ], 3600);

            return;
        }

        Cache::put($statusKey, [
            'status' => 'processing',
            'message' => "Extracting ({$after} leads)",
            'total' => $after
        ], 3600);

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