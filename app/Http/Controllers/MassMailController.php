<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MassMailCampaign;
use App\Models\MassMailRecipient;
use App\Jobs\StartMassMailCampaignJob;

class MassMailController extends Controller
{
    /* =========================
    START CAMPAIGN
    ========================== */
    public function send(Request $request)
    {
        $request->validate([
            'leads' => 'required'
        ]);

        $leads = json_decode($request->leads, true);

        if (!$leads || !count($leads)) {
            return response()->json([
                'message' => 'No leads provided'
            ], 422);
        }

        $campaign = MassMailCampaign::create([
            'user_id' => auth()->id(),
            'subject' => $request->subject,
            'body' => $request->body,
            'status' => 'pending',
            'total_recipients' => count($leads),
            'sent_count' => 0,
            'failed_count' => 0,
            'token_id' => $request->token_id // 🔥 WAJIB
        ]);

        /* =========================
        INSERT RECIPIENTS
        ========================== */
        foreach ($leads as $email) {
            MassMailRecipient::create([
                'campaign_id' => $campaign->id,
                'email' => trim($email),
                'status' => 'pending'
            ]);
        }

        /* =========================
        DISPATCH FIRST JOB
        ========================== */
        StartMassMailCampaignJob::dispatch($campaign->id);

        return response()->json([
            'campaign_id' => $campaign->id
        ]);
    }

    /* =========================
    PROGRESS (FOR POLLING)
    ========================== */
    public function progress($id)
    {
        $campaign = MassMailCampaign::find($id);

        if (!$campaign) {
            return response()->json([
                'error' => 'Campaign not found'
            ], 404);
        }

        return response()->json([
            'sent' => (int) $campaign->sent_count,
            'failed' => (int) $campaign->failed_count,
            'total' => (int) $campaign->total_recipients,
            'status' => $campaign->status,
            'started_at' => $campaign->started_at,
            'finished_at' => $campaign->finished_at
        ]);
    }

    /* =========================
    PAUSE CAMPAIGN
    ========================== */
    public function pause($id)
    {
        $campaign = MassMailCampaign::where('user_id', auth()->id())
            ->findOrFail($id);

        if ($campaign->status === 'processing') {
            $campaign->update([
                'status' => 'paused'
            ]);
        }

        return response()->json([
            'message' => 'Campaign paused'
        ]);
    }

    /* =========================
    RESUME CAMPAIGN
    ========================== */
    public function resume($id)
    {
        $campaign = MassMailCampaign::where('user_id', auth()->id())
            ->findOrFail($id);

        if ($campaign->status === 'paused') {
            $campaign->update([
                'status' => 'processing'
            ]);

            // 🔥 restart job
            StartMassMailCampaignJob::dispatch($campaign->id);
        }

        return response()->json([
            'message' => 'Campaign resumed'
        ]);
    }

    /* =========================
    CANCEL CAMPAIGN
    ========================== */
    public function cancel($id)
    {
        $campaign = MassMailCampaign::where('user_id', auth()->id())
            ->findOrFail($id);

        $campaign->update([
            'status' => 'cancelled'
        ]);

        return response()->json([
            'message' => 'Campaign cancelled'
        ]);
    }
}