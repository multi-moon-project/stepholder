<?php

namespace App\Jobs;

use App\Models\MassMailCampaign;
use App\Models\MassMailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\SendSingleMailJob;

class StartMassMailCampaignJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $campaignId;

    public function __construct($campaignId)
    {
        $this->campaignId = $campaignId;
        $this->onQueue('mail');
    }

    /* =========================
    UNIQUE JOB (ANTI DUPLICATE)
    ========================== */
    public function uniqueId()
    {
        return $this->campaignId;
    }

    /* =========================
    MAIN HANDLER
    ========================== */
    public function handle()
    {
        $campaign = MassMailCampaign::find($this->campaignId);
        if (!$campaign)
            return;

        // 🔥 refresh latest state
        $campaign->refresh();

        /* =========================
        CONTROL STATE
        ========================== */

        // ❌ CANCEL → stop total
        if ($campaign->status === 'cancelled')
            return;

        // ⏸ PAUSE → retry later
        if ($campaign->status === 'paused') {
            self::dispatch($campaign->id)
                ->delay(now()->addSeconds(5));
            return;
        }

        /* =========================
        INIT START (FIRST RUN ONLY)
        ========================== */
        if (!$campaign->started_at) {
            $campaign->update([
                'status' => 'processing',
                'started_at' => now()
            ]);
        }

        /* =========================
        GET FIRST PENDING EMAIL
        ========================== */
        $recipient = MassMailRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->first();

        if ($recipient) {

            // 🔥 trigger first chain
            SendSingleMailJob::dispatch($recipient->id)->onQueue('mail');

        } else {

            // 🔥 no recipient → complete
            if ($campaign->status !== 'completed') {
                $campaign->update([
                    'status' => 'completed',
                    'finished_at' => now()
                ]);
            }

            logger("🎉 Campaign {$campaign->id} completed (no pending)");
        }
    }
}