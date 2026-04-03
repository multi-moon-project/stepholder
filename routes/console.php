<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Http;
use App\Services\MicrosoftGraphService;

/*
|--------------------------------------------------------------------------
| Artisan Command
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| TOKEN REFRESH (SUDAH ADA)
|--------------------------------------------------------------------------
*/

Schedule::command('tokens:refresh')->everyMinute();

/*
|--------------------------------------------------------------------------
| 🔥 AUTO RENEW MICROSOFT GRAPH WEBHOOK
|--------------------------------------------------------------------------
*/

Schedule::call(function () {

    $subs = \App\Models\GraphSubscription::all();

    $service = app(MicrosoftGraphService::class);

    foreach ($subs as $sub) {

        if (now()->addMinutes(10)->greaterThan($sub->expires_at)) {

            try {

                $service->createSubscription($sub->token_id);

            } catch (\Throwable $e) {

                \Log::error('Renew gagal', [
                    'token_id' => $sub->token_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

})->everyFiveMinutes();