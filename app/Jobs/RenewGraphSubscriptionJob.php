<?php

namespace App\Jobs;

use App\Models\GraphSubscription;
use App\Services\MicrosoftGraphService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RenewGraphSubscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public $subId;

    public $tries = 3;
    public $timeout = 30;

    public function __construct($subId)
    {
        $this->subId = $subId;
        $this->onQueue('graph-renew');
    }

    public function handle(MicrosoftGraphService $service)
    {
        $sub = GraphSubscription::find($this->subId);

        if (!$sub) {
            return;
        }

        // Double check biar aman (hindari race condition)
        if (now()->addMinutes(10)->lessThan($sub->expires_at)) {
            return;
        }

        try {
            $response = $service->createSubscription($sub->token_id);

            // ⚠️ Pastikan service return expiry
            if (isset($response['expirationDateTime'])) {
                $sub->update([
                    'expires_at' => $response['expirationDateTime'],
                ]);
            }

        } catch (\Throwable $e) {

            Log::error('Renew subscription gagal', [
                'subscription_id' => $this->subId,
                'token_id' => $sub->token_id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // biar retry jalan
        }
    }
}