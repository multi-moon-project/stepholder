<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\GraphSubscription;
use App\Jobs\RenewGraphSubscriptionJob;

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
| 🔥 AUTO RENEW MICROSOFT GRAPH WEBHOOK (PRODUCTION READY)
|--------------------------------------------------------------------------
*/

Schedule::call(function () {

    GraphSubscription::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<=', now()->addMinutes(10))
        ->chunkById(100, function ($subs) {

            foreach ($subs as $sub) {
                RenewGraphSubscriptionJob::dispatch($sub->id)->onQueue('graph-renew');
            }

        });

})->name('graph-renew-subscription')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer(); // penting kalau pakai multi server