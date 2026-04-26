<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RenewGraphSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:renew-graph-subscription';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $subId = cache('graph_subscription_id');

        if (!$subId) {
            \Log::warning("No subscription ID found");
            return;
        }

        $graph = app(\App\Services\MicrosoftGraphService::class);

        $token = (new \ReflectionClass($graph))
            ->getMethod('getAccessToken')
            ->invoke($graph);

        $expiry = now()->addMinutes(60)->toIso8601String(); // e.g., 2026-04-26T12:21:17+00:00

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->patch("https://graph.microsoft.com/v1.0/subscriptions/{$subId}", [
                "expirationDateTime" => $expiry
            ]);

        \Log::info("Subscription renewed", [
            'status' => $response->status(),
            'body' => $response->json()
        ]);
    }
}
