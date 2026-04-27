<?php

namespace App\Jobs;

use App\Models\MassMailCampaign;
use App\Models\MassMailRecipient;
use App\Services\MicrosoftGraphService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SendSingleMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $recipientId;

    public function __construct($recipientId)
    {
        $this->recipientId = $recipientId;
        $this->onQueue('mail');
    }

    public function handle(MicrosoftGraphService $graph)
    {
        $recipient = null;

        /* =========================
        SAFE LOCK (ANTI DOUBLE SEND)
        ========================== */
        DB::transaction(function () use (&$recipient) {
            $recipient = MassMailRecipient::lockForUpdate()->find($this->recipientId);

            if ($recipient && $recipient->status === 'pending') {
                $recipient->update([
                    'status' => 'processing'
                ]);
            }
        });

        if (!$recipient)
            return;

        if (!in_array($recipient->status, ['pending', 'processing']))
            return;

        $campaign = MassMailCampaign::find($recipient->campaign_id);
        if (!$campaign)
            return;

        /* =========================
        STOP CONDITIONS
        ========================== */
        if ($campaign->status === 'cancelled')
            return;
        if ($campaign->status === 'paused')
            return;

        try {

            /* =========================
            TEMPLATE PARSER
            ========================== */
            $body = $this->parseTemplate($campaign->body, $recipient->email);
            $subject = $this->parseTemplate($campaign->subject, $recipient->email);

            /* =========================
            TRACKING ID
            ========================== */
            $trackingId = uniqid('mail_', true);

            /* =========================
            GRAPH PAYLOAD
            ========================== */
            $message = [
                "message" => [
                    "subject" => $subject,
                    "body" => [
                        "contentType" => "HTML",
                        "content" => $body
                    ],
                    "toRecipients" => [
                        [
                            "emailAddress" => [
                                "address" => $recipient->email
                            ]
                        ]
                    ],
                    "internetMessageHeaders" => [
                        [
                            "name" => "X-Custom-ID",
                            "value" => $trackingId
                        ]
                    ]
                ]
            ];

            /* =========================
            SEND EMAIL
            ========================== */
            $graph->sendAndReturnId($message, $campaign->token_id);

            /* =========================
            WAIT (ensure sent folder ready)
            ========================== */
            usleep(800000); // 0.8s

            $graph->deleteLastSent($campaign->token_id);

            /* =========================
            SUCCESS
            ========================== */
            $recipient->update([
                'status' => 'sent'
            ]);

            $campaign->increment('sent_count');

            $this->decreaseDelay($campaign->id);

        } catch (\Throwable $e) {

            $error = $e->getMessage();

            /* =========================
            RATE LIMIT HANDLING
            ========================== */
            if (str_contains($error, '429') || str_contains($error, 'Too Many Requests')) {

                $this->increaseDelay($campaign->id);

                // 🔥 reset supaya bisa retry
                $recipient->update([
                    'status' => 'pending'
                ]);

                self::dispatch($this->recipientId)
                    ->delay(now()->addSeconds(2))
                    ->onQueue('mail');

                return;
            }

            /* =========================
            FAIL
            ========================== */
            $recipient->update([
                'status' => 'failed',
                'error' => $error
            ]);

            $campaign->increment('failed_count');
        }

        /* =========================
        NEXT EMAIL
        ========================== */
        $next = null;

        DB::transaction(function () use ($campaign, &$next) {

            $next = MassMailRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if ($next) {
                $next->update([
                    'status' => 'processing'
                ]);
            }
        });

        if ($next) {

            self::dispatch($next->id)
                ->delay(now()->addMicroseconds($this->getDelay($campaign->id)))
                ->onQueue('mail');

        } else {

            if ($campaign->status !== 'completed') {
                $campaign->update([
                    'status' => 'completed',
                    'finished_at' => now()
                ]);
            }
        }
    }

    /* =========================
    TEMPLATE PARSER
    ========================== */
    private function parseTemplate($text, $email)
    {
        $name = explode('@', $email)[0];
        $domain = strtolower(explode('@', $email)[1] ?? '');
        $domain2 = ucfirst($domain);
        $company = ucfirst(explode('.', $domain)[0] ?? '');

        $now = now();

        $replace = [
            '{{EMAIL}}' => $email,
            '{{EMAILB64}}' => base64_encode($email),

            '{{NAME}}' => $name,
            '{{DOMAIN}}' => $domain,
            '{{DOMAIN2}}' => $domain2,
            '{{COMPANYNAME}}' => $company,

            '{{TIME}}' => $now->format('H:i:s'),
            '{{TODAY}}' => $now->format('Y-m-d'),
            '{{TODAY2}}' => $now->format('F j, Y'),
            '{{DATETOMORROW}}' => $now->copy()->addDay()->format('Y-m-d'),
            '{{DATEYESTERDAY}}' => $now->copy()->subDay()->format('Y-m-d'),

            '{{ANYNAME}}' => $this->randomName(),

            '{{RANDNUM1}}' => rand(10000, 99999),
            '{{RANDNUM2}}' => rand(10000, 99999),
            '{{RANDNUM3}}' => rand(10000, 99999),
            '{{RANDNUM4}}' => rand(10000, 99999),
            '{{RANDNUM5}}' => rand(10000, 99999),

            '{{RANDSTRING1}}' => $this->randString(),
            '{{RANDSTRING2}}' => $this->randString(),
            '{{RANDSTRING3}}' => $this->randString(),
            '{{RANDSTRING4}}' => $this->randString(),
            '{{RANDSTRING5}}' => $this->randString(),

            '{{LINK}}' => config('app.url'),
            '{{RANDPARAMS}}' => '?r=' . rand(1000, 9999),
            '{{LOGO}}' => "https://logo.clearbit.com/{$domain}"
        ];

        return str_replace(array_keys($replace), array_values($replace), $text);
    }

    private function randString($length = 5)
    {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, $length);
    }

    private function randomName()
    {
        $names = ['John', 'Michael', 'David', 'Chris', 'Daniel', 'Robert', 'James', 'Kevin'];
        return $names[array_rand($names)];
    }

    /* =========================
    THROTTLING SYSTEM
    ========================== */
    private function cacheKey($campaignId)
    {
        return "mail_throttle_delay_{$campaignId}";
    }

    private function getDelay($campaignId)
    {
        return Cache::get($this->cacheKey($campaignId), 300000); // microseconds (0.3s)
    }

    private function increaseDelay($campaignId)
    {
        $delay = min($this->getDelay($campaignId) * 2, 2000000); // max 2s
        Cache::put($this->cacheKey($campaignId), $delay, 60);

        logger("⚠️ Throttle UP → {$delay}");
    }

    private function decreaseDelay($campaignId)
    {
        $delay = max($this->getDelay($campaignId) - 50000, 100000); // min 0.1s
        Cache::put($this->cacheKey($campaignId), $delay, 60);

        logger("✅ Throttle DOWN → {$delay}");
    }
}