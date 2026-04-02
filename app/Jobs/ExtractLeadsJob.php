<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use App\Services\MicrosoftGraphService;

class ExtractLeadsJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    protected $userId;

    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    public function handle(MicrosoftGraphService $graph)
    {
        $key = 'leads_' . $this->userId;
        $statusKey = 'leads_status_' . $this->userId;
        $nextKey = 'graph_next_' . $this->userId;

        try {

            Cache::put($statusKey, [
                'status' => 'processing',
                'message' => 'Fetching batch...',
            ], 3600);

            $nextLink = Cache::get($nextKey);

            $result = $graph->fetchBatch($nextLink);

            $newLeads = $result['data'] ?? [];
            $nextLink = $result['next'] ?? null;

            $existing = Cache::get($key, []);

            // 🔥 HITUNG SEBELUM
            $before = count($existing);

            $merged = collect($existing)
                ->merge($newLeads)
                ->unique('email')
                ->values()
                ->all();

            // 🔥 HITUNG SETELAH
            $after = count($merged);

            Cache::put($key, $merged, 3600);

            // 🔥 LOG
            \Log::info("Leads before: $before | after: $after | new: " . count($newLeads));

            /*
            ==================================================
            🚨 STOP CONDITION (ANTI SOFT LOOP)
            ==================================================
            */

           // ❌ 1. Tidak ada data baru
if ($after === $before && !$nextLink) {

    \Log::warning("STOP: No new leads (END OF DATA)");

    Cache::forget($nextKey);
    Cache::forget('leads_lock_' . $this->userId);

    Cache::put($statusKey, [
        'status' => 'done',
        'message' => 'No more new unique leads',
        'total' => $after
    ], 3600);

    return;
}

            // ❌ 2. Batch kosong total
            if (empty($newLeads)) {

                \Log::warning("STOP: Empty batch from API");

                Cache::forget($nextKey);
                Cache::forget('leads_lock_' . $this->userId);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'No more data from API',
                    'total' => $after
                ], 3600);

                return;
            }

            /*
            ==================================================
            🔁 LANJUT ATAU SELESAI
            ==================================================
            */

            if ($nextLink) {

                Cache::put($nextKey, $nextLink, 3600);

                Cache::put($statusKey, [
                    'status' => 'continue',
                    'message' => 'Next batch...',
                    'total' => $after
                ], 3600);

                // 🔥 Delay kecil (hindari 429)
                usleep(500000); // 0.5 detik

                self::dispatch($this->userId);

            } else {

                \Log::info("DONE: No nextLink");

                Cache::forget($nextKey);
                Cache::forget('leads_lock_' . $this->userId);

                Cache::put($statusKey, [
                    'status' => 'done',
                    'message' => 'All leads extracted',
                    'total' => $after
                ], 3600);
            }

        } catch (\Throwable $e) {

            \Log::error("ExtractLeadsJob FAILED: " . $e->getMessage());

            Cache::forget('leads_lock_' . $this->userId);

            Cache::put($statusKey, [
                'status' => 'failed',
                'message' => $e->getMessage()
            ], 3600);
        }
    }
}