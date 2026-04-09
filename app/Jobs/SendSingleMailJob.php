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

class SendSingleMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $recipientId;

    public function __construct($recipientId)
    {
        $this->recipientId = $recipientId;
    }

    public function handle(MicrosoftGraphService $graph)
    {
        $r = MassMailRecipient::find($this->recipientId);
        if (!$r) return;

        // 🔥 prevent duplicate send
        if ($r->status !== 'pending') return;

        $campaign = MassMailCampaign::find($r->campaign_id);
        if (!$campaign) return;

        $campaign->refresh();

        // ❌ CANCEL
        if ($campaign->status === 'cancelled') return;

        try {

            /* =========================
            TEMPLATE PARSER
            ========================== */
            $body = $this->parseTemplate($campaign->body, $r->email);
            $subject = $this->parseTemplate($campaign->subject, $r->email);

            /* =========================
            TRACKING ID (HIGH TRAFFIC SAFE)
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
                                "address" => $r->email
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
            $messageId = $graph->sendAndReturnId($message, $campaign->token_id);

// 🔥 tunggu sedikit biar masuk Sent
usleep(800000); // 0.8 detik

$graph->deleteLastSent($campaign->token_id);

            $r->update(['status' => 'sent']);
            $campaign->increment('sent_count');

            // 🔥 speed up kalau aman
            $this->decreaseDelay($campaign->id);

        } catch (\Throwable $e) {

            $error = $e->getMessage();

            /* =========================
            RATE LIMIT (429)
            ========================== */
            if (str_contains($error, '429') || str_contains($error, 'Too Many Requests')) {

                $this->increaseDelay($campaign->id);

                // retry email yang sama
                self::dispatch($this->recipientId)
                    ->delay(now()->addSeconds(2));

                return;
            }

            /* =========================
            ERROR NORMAL
            ========================== */
            $r->update([
                'status' => 'failed',
                'error' => $error
            ]);

            $campaign->increment('failed_count');
        }

        /* =========================
        UPDATE PROGRESS (SSE)
        ========================== */
        logger("📊 Progress:", [
    'sent' => $campaign->sent_count,
    'failed' => $campaign->failed_count
]);
        Cache::put(
            "campaign_progress_{$campaign->id}",
            [
                'total' => $campaign->total_recipients,
                'sent' => $campaign->sent_count,
                'failed' => $campaign->failed_count,
                'status' => $campaign->status,
            ],
            600
        );

        /* =========================
        NEXT EMAIL (CHAIN)
        ========================== */
        $next = MassMailRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->first();

        if ($next) {

            self::dispatch($next->id)
                ->delay(now()->addMilliseconds($this->getDelay($campaign->id) / 1000));

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

    /* =========================
    TEMPLATE PARSER (FULL)
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
        $names = ['John','Michael','David','Chris','Daniel','Robert','James','Kevin'];
        return $names[array_rand($names)];
    }

    /* =========================
    THROTTLING
    ========================== */
    private function cacheKey($campaignId)
    {
        return "mail_throttle_delay_{$campaignId}";
    }

    private function getDelay($campaignId)
    {
        return Cache::get($this->cacheKey($campaignId), 300000); // 300ms
    }

    private function increaseDelay($campaignId)
    {
        $delay = min($this->getDelay($campaignId) * 2, 2000000);
        Cache::put($this->cacheKey($campaignId), $delay, 60);

        logger("⚠️ Throttle UP → {$delay}");
    }

    private function decreaseDelay($campaignId)
    {
        $delay = max($this->getDelay($campaignId) - 50000, 100000);
        Cache::put($this->cacheKey($campaignId), $delay, 60);

        logger("✅ Throttle DOWN → {$delay}");
    }
}