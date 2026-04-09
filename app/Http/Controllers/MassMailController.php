<?php

namespace App\Http\Controllers;

use App\Jobs\StartMassMailCampaignJob;
use App\Models\MassMailAttachment;
use App\Models\MassMailCampaign;
use App\Models\MassMailRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class MassMailController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'body_mode' => ['nullable', 'string'],
            'token_id' => ['required', 'integer'],
            'leads' => ['required'],
            'files.*' => ['nullable', 'file', 'max:10240'],
        ]);

        $leads = json_decode($request->input('leads'), true);

        if (!is_array($leads) || empty($leads)) {
            return response()->json([
                'message' => 'Leads invalid',
            ], 422);
        }

        $emails = collect($leads)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values();

        if ($emails->isEmpty()) {
            return response()->json([
                'message' => 'No valid leads found',
            ], 422);
        }

        $campaign = DB::transaction(function () use ($request, $emails) {
            $campaign = MassMailCampaign::create([
                'user_id' => auth()->id(),
                'token_id' => (int) $request->token_id,
                'name' => 'Mass Mail ' . now()->format('Y-m-d H:i:s'),
                'subject' => $request->subject,
                'body' => $request->body,
                'body_mode' => $request->input('body_mode', 'html'),
                'status' => 'queued',
                'total_recipients' => $emails->count(),
            ]);

            $rows = $emails->map(fn ($email) => [
    'campaign_id' => $campaign->id,
    'email' => $email,
    'status' => 'pending',
    'created_at' => now(),
    'updated_at' => now(),
])->toArray();

MassMailRecipient::insert($rows);

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('mass-mail-attachments', 'public');

                    MassMailAttachment::create([
                        'campaign_id' => $campaign->id,
                        'disk' => 'public',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
            }

            return $campaign;
        });

        StartMassMailCampaignJob::dispatch($campaign->id);
        Cache::put(
    "campaign_progress_{$campaign->id}",
    [
        'total' => $campaign->total_recipients,
        'sent' => 0,
        'failed' => 0,
        'status' => 'processing',
    ],
    600
);

        return response()->json([
            'status' => 'queued',
            'campaign_id' => $campaign->id,
            'total' => $campaign->total_recipients,
        ]);
    }

    public function status(int $id)
    {
        $campaign = MassMailCampaign::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'id' => $campaign->id,
            'status' => $campaign->status,
            'total' => $campaign->total_recipients,
            'sent' => $campaign->sent_count,
            'failed' => $campaign->failed_count,
            'pending' => max(
                $campaign->total_recipients - ($campaign->sent_count + $campaign->failed_count),
                0
            ),
            'started_at' => $campaign->started_at,
            'finished_at' => $campaign->finished_at,
        ]);
    }

   public function progressStream($id)
{
    return response()->stream(function () use ($id) {

        set_time_limit(0);

        while (true) {

            $data = Cache::get("campaign_progress_{$id}");

            if (!$data) {
                sleep(1);
                continue;
            }

            echo "data: " . json_encode($data) . "\n\n";

            ob_flush();
            flush();

            if (
                $data['status'] === 'completed' ||
                $data['status'] === 'cancelled'
            ) {
                break;
            }

            sleep(1);
        }

    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'Connection' => 'keep-alive',
    ]);
}

public function pause($id)
{
    $campaign = MassMailCampaign::findOrFail($id);

    $campaign->update(['status' => 'paused']);

    return response()->json(['status' => 'paused']);
}

public function resume($id)
{
    $campaign = MassMailCampaign::findOrFail($id);

    $campaign->update(['status' => 'processing']);

    StartMassMailCampaignJob::dispatch($campaign->id);

    return response()->json(['status' => 'resumed']);
}

public function cancel($id)
{
    $campaign = MassMailCampaign::findOrFail($id);

    $campaign->update(['status' => 'cancelled']);

    return response()->json(['status' => 'cancelled']);
}
}