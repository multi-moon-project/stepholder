<?php

namespace App\Jobs;

use App\Models\MassMailCampaign;
use App\Models\MassMailRecipient;
use App\Services\MicrosoftGraphService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SendMassMailChunkJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries = 3;

    public function __construct(
        public int $campaignId,
        public int $chunkSize = 10
    ) {}

    public function handle(MicrosoftGraphService $graph): void
    {
        $campaign = MassMailCampaign::with('attachments')->find($this->campaignId);

        if (!$campaign) {
            return;
        }

        if (!in_array($campaign->status, ['queued', 'processing'])) {
            return;
        }

        $recipients = MassMailRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->limit($this->chunkSize)
            ->get();

        if ($recipients->isEmpty()) {
            $campaign->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
            return;
        }

        foreach ($recipients as $recipient) {
            $this->sendToRecipient($campaign, $recipient, $graph);
        }

        $remaining = MassMailRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->exists();

        if ($remaining) {
            self::dispatch($campaign->id, $this->chunkSize);
        } else {
            $campaign->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
        }
    }

    protected function sendToRecipient(
        MassMailCampaign $campaign,
        MassMailRecipient $recipient,
        MicrosoftGraphService $graph
    ): void {
        $recipient->update([
            'status' => 'processing',
            'attempts' => $recipient->attempts + 1,
        ]);

        try {
            $subject = $this->parseTemplate($campaign->subject, $recipient->email);
            $body = $this->parseTemplate($campaign->body, $recipient->email);

            $attachments = [];

            foreach ($campaign->attachments as $file) {
                if (!Storage::disk($file->disk)->exists($file->path)) {
                    continue;
                }

                $attachments[] = [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $file->original_name,
                    'contentBytes' => base64_encode(
                        Storage::disk($file->disk)->get($file->path)
                    ),
                ];
            }

            $payload = [
                'message' => [
                    'subject' => $subject,
                    'body' => [
                        'contentType' => 'HTML',
                        'content' => $body,
                    ],
                    'toRecipients' => [
                        [
                            'emailAddress' => [
                                'address' => $recipient->email,
                            ],
                        ],
                    ],
                ],
                'saveToSentItems' => true,
            ];

            if (!empty($attachments)) {
                $payload['message']['attachments'] = $attachments;
            }

            $graph->sendMail($payload, $campaign->token_id);

            $recipient->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ]);

            $campaign->increment('sent_count');
        } catch (\Throwable $e) {
            $recipient->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $campaign->increment('failed_count');
        }
    }

    protected function parseTemplate(string $text, string $email): string
    {
        $name = strstr($email, '@', true) ?: $email;
        $domain = strtolower(substr(strrchr($email, "@"), 1) ?: '');

        $replacements = [
            '{{EMAIL}}' => $email,
            '{{NAME}}' => $name,
            '{{DOMAIN}}' => $domain,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $text
        );
    }
}