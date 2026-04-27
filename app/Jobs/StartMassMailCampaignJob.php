<?php

namespace App\Jobs;

use App\Models\MassMailCampaign;
use App\Models\MassMailRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class StartMassMailCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $campaignId;

    public function __construct($campaignId)
    {
        $this->campaignId = $campaignId;
        $this->onQueue('mail');
    }

    public function handle()
    {
        $campaign = MassMailCampaign::find($this->campaignId);
        if (!$campaign)
            return;

        /* =========================
        CONTROL STATE
        ========================== */
        if ($campaign->status === 'cancelled')
            return;

        if ($campaign->status === 'paused')
            return;

        /* =========================
        INIT START
        ========================== */
        if (!$campaign->started_at) {
            $campaign->update([
                'status' => 'processing',
                'started_at' => now()
            ]);
        }

        /* =========================
        SAFE PICK RECIPIENT
        ========================== */
        $recipient = null;

        \DB::transaction(function () use ($campaign, &$recipient) {

            $recipient = MassMailRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($recipient) {
                $recipient->update([
                    'status' => 'processing'
                ]);
            }
        });

        /* =========================
        PROCESS
        ========================== */
        if ($recipient) {

            \App\Jobs\SendSingleMailJob::dispatch($recipient->id)
                ->onQueue('mail');

        } else {

            if ($campaign->status !== 'completed') {
                $campaign->update([
                    'status' => 'completed',
                    'finished_at' => now()
                ]);
            }

            logger("🎉 Campaign {$campaign->id} completed");
        }
    }
}