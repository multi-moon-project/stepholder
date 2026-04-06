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

            // SAFETY LIMIT
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

            // LOAD EXISTING LEADS
            $existing = Cache::get($key, []);
            $before   = count($existing);
            Log::info("[LEADS_JOB] EXISTING", compact('before'));
            Cache::put($statusKey, ['status'=>'processing','message'=>"Fetching batch...",'total'=>$before],3600);

            // LOAD STATE
            $state = Cache::get($stateKey);
            Log::info("[LEADS_JOB] STATE", ['state_exists'=>!!$state]);

            // FETCH BATCH
            $result = $graph->fetchBatch($state, $this->tokenId);
            $state  = $result['state'] ?? null;

            // SIMPAN STATE
            if ($state) Cache::put($stateKey, $state, 3600);
            else Cache::forget($stateKey);

            $newLeads = collect($result['data'] ?? [])->filter(fn($x)=>!empty($x['email']))->values()->all();
            Log::info("[LEADS_JOB] NEW BATCH", ['count'=>count($newLeads)]);

            // MERGE UNIQUE
            $merged = collect($existing)->merge($newLeads)->unique('email')->values()->all();
            $after = count($merged);
            Cache::put($key,$merged,3600);
            Log::info("[LEADS_JOB] MERGED", ['before'=>$before,'after'=>$after]);

            // STOP CONDITION
            if (($after === $before && !$state) || empty($newLeads)) {
                Log::warning("[LEADS_JOB] STOP CONDITION HIT");
                Cache::forget($stateKey);
                Cache::forget($lockKey);
                Cache::forget($runKey);
                $message = empty($newLeads) ? 'No data from API' : 'No new unique leads';
                Cache::put($statusKey, ['status'=>'done','message'=>$message,'total'=>$after],3600);
                return;
            }

            // UPDATE STATUS
            Cache::put($statusKey, ['status'=>'processing','message'=>"Extracting ($after leads)",'total'=>$after],3600);

            // CONTINUE NEXT JOB
            Log::info("[LEADS_JOB] CONTINUE NEXT JOB");
            dispatch(new self($this->tokenId))->onConnection('redis')->onQueue('default');

        } catch (\Throwable $e) {
            Log::error("[LEADS_JOB] FAILED", ['error'=>$e->getMessage(),'line'=>$e->getLine(),'file'=>$e->getFile()]);
            Cache::forget($lockKey);
            Cache::forget($runKey);
            Cache::put($statusKey, ['status'=>'failed','message'=>$e->getMessage()],3600);
        }
    }
}