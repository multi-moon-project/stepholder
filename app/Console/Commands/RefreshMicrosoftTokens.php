<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Token;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RefreshMicrosoftTokens extends Command
{
    protected $signature = 'tokens:refresh';
    protected $description = 'Auto refresh Microsoft tokens';

    public function handle()
    {
        $tokens = Token::where('status', 'active')
            ->whereNotNull('refresh_token')
            ->where('expires_at', '<=', now()->addMinutes(5))
            ->get();

        if ($tokens->isEmpty()) {
            $this->info('No tokens need refresh.');
            return self::SUCCESS;
        }

        foreach ($tokens as $token) {
            $this->info("Refreshing token ID: {$token->id}");

            try {
                $response = Http::asForm()
                    ->timeout(10)
                    ->retry(3, 200)
                    ->post(
                        'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                        [
                            'client_id' => 'd3590ed6-52b3-4102-aeff-aad2292ab01c',
                            'grant_type' => 'refresh_token',
                            'refresh_token' => $token->refresh_token,
                            'scope' => 'https://graph.microsoft.com/.default',
                        ]
                    );

                $data = $response->json();

                if (!isset($data['access_token'])) {
                    $error = $data['error'] ?? 'unknown';
                    $errorDescription = $data['error_description'] ?? null;

                    $this->error("Failed token {$token->id} - {$error}");

                    Log::warning('Microsoft token refresh failed', [
                        'token_id' => $token->id,
                        'error' => $error,
                        'error_description' => $errorDescription,
                        'response' => $data,
                    ]);

                    if ($error === 'invalid_grant') {
                        $token->update([
                            'status' => 'dead',
                        ]);
                    }

                    usleep(200000);
                    continue;
                }

                $token->update([
                    'access_token' => $data['access_token'],
                    'refresh_token' => $data['refresh_token'] ?? $token->refresh_token,
                    'expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
                    'status' => 'active',
                ]);

                $this->info("Token refreshed: {$token->id}");

                Log::info('Microsoft token refreshed', [
                    'token_id' => $token->id,
                    'expires_at' => $token->fresh()->expires_at,
                ]);
            } catch (\Throwable $e) {
                $this->error("Error token {$token->id}: " . $e->getMessage());

                Log::error('Microsoft token refresh exception', [
                    'token_id' => $token->id,
                    'message' => $e->getMessage(),
                ]);
            }

            usleep(200000);
        }

        return self::SUCCESS;
    }
}