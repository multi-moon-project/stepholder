<?php

namespace App\Jobs;

use App\Models\DeviceLogin;
use App\Models\Account;
use App\Models\Token;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class PollMicrosoftDeviceLoginJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 50;
    public $backoff = 5;

    public $loginId;

    public function __construct($loginId)
    {
        $this->loginId = $loginId;
        $this->onQueue('auth');
    }

    public function retryUntil()
    {
        return now()->addMinutes(10);
    }

    public function handle(): void
    {
        $login = DeviceLogin::find($this->loginId);

        if (!$login)
            return;

        if ($login->completed)
            return;

        if ($login->expires_at && now()->gt($login->expires_at)) {
            $login->update(['status' => 'expired']);
            return;
        }

        if ($login->next_poll_at && now()->lt($login->next_poll_at)) {
            $this->release(5);
            return;
        }

        $login->update([
            'status' => 'polling',
            'last_polled_at' => now()
        ]);

        // =============================
        // TOKEN REQUEST
        // =============================
        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/common/oauth2/v2.0/token",
            [
                "grant_type" => "urn:ietf:params:oauth:grant-type:device_code",
                "client_id" => "d3590ed6-52b3-4102-aeff-aad2292ab01c",
                "device_code" => $login->device_code
            ]
        );

        $data = $response->json();

        \Log::info("TOKEN RESPONSE", $data);

        // =============================
        // HANDLE 429
        // =============================
        if ($response->status() == 429) {

            $retryAfter = $response->header('Retry-After') ?? 10;

            $login->update([
                'status' => 'throttled',
                'last_error' => '429',
                'next_poll_at' => now()->addSeconds($retryAfter)
            ]);

            $this->release($retryAfter);
            return;
        }

        // =============================
        // AUTH PENDING
        // =============================
        if (isset($data['error']) && $data['error'] === 'authorization_pending') {

            $login->update([
                'status' => 'pending',
                'next_poll_at' => now()->addSeconds($login->interval)
            ]);

            $this->release($login->interval);
            return;
        }

        // =============================
        // SLOW DOWN
        // =============================
        if (isset($data['error']) && $data['error'] === 'slow_down') {

            $login->increment('interval');

            $login->update([
                'status' => 'throttled',
                'last_error' => 'slow_down',
                'next_poll_at' => now()->addSeconds($login->interval)
            ]);

            $this->release($login->interval);
            return;
        }

        // =============================
        // EXPIRED
        // =============================
        if (isset($data['error']) && $data['error'] === 'invalid_grant') {

            $login->update(['status' => 'expired']);
            return;
        }

        // =============================
        // SUCCESS → AMBIL USER
        // =============================
        if (isset($data['access_token'])) {

            $accessToken = $data['access_token'];

            \Log::info("ACCESS TOKEN OK");

            // =============================
            // GRAPH API
            // =============================
            $graphUser = null;

            $graphUser = null;

            for ($i = 0; $i < 10; $i++) {

                sleep(3);

                $graphResponse = Http::withToken($accessToken)
                    ->timeout(20)
                    ->get('https://graph.microsoft.com/v1.0/me');

                \Log::info("GRAPH TRY", [
                    'attempt' => $i,
                    'status' => $graphResponse->status(),
                    'body' => $graphResponse->json()
                ]);

                if ($graphResponse->status() == 200) {
                    $graphUser = $graphResponse->json();
                    break;
                }

                if ($graphResponse->status() == 401) {
                    // token belum siap
                    continue;
                }

                if ($graphResponse->status() == 429) {
                    sleep(5);
                    continue;
                }

                // error lain → tetap retry
            }

            if (!$graphUser) {

                \Log::warning("GRAPH NOT READY, RETRY LATER");

                $login->update([
                    'status' => 'waiting_graph',
                    'next_poll_at' => now()->addSeconds(5)
                ]);

                $this->release(5);
                return;
            }

            // =============================
            // PARSE USER
            // =============================
            $email = $graphUser['mail']
                ?? $graphUser['userPrincipalName']
                ?? null;

            $name = $graphUser['displayName'] ?? 'Microsoft User';

            \Log::info("USER DATA", [
                'email' => $email,
                'name' => $name
            ]);

            if (!$email) {
                $login->update([
                    'status' => 'error',
                    'last_error' => 'no_email'
                ]);
                return;
            }

            // =============================
            // SAVE ACCOUNT
            // =============================
            Account::firstOrCreate([
                'user_id' => $login->user_id,
                'provider' => 'microsoft'
            ]);

            // =============================
            // 🔥 SAVE TOKEN (FIXED)
            // =============================
            try {

                \Log::info("SAVE TOKEN START");

                Token::create([
                    'user_id' => $login->user_id,
                    'email' => $email,
                    'name' => $name,
                    'access_token' => $accessToken,
                    'refresh_token' => $data['refresh_token'] ?? null,
                    'expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                    'status' => 'active'
                ]);

                \Log::info("SAVE TOKEN SUCCESS");

            } catch (\Exception $e) {

                \Log::error("SAVE TOKEN ERROR", [
                    'message' => $e->getMessage()
                ]);

                return;
            }

            // =============================
            // MARK SUCCESS
            // =============================
            $login->update([
                'status' => 'success',
                'completed' => true
            ]);

            \Log::info("LOGIN + SAVE SUCCESS", [
                'email' => $email
            ]);

            return;
        }

        // =============================
        // ERROR FALLBACK
        // =============================
        $login->increment('retry_count');

        $login->update([
            'status' => 'error',
            'last_error' => json_encode($data),
            'next_poll_at' => now()->addSeconds(5)
        ]);

        $this->release(5);
    }
}