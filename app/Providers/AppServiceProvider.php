<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use App\Services\MicrosoftGraphService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
   public function boot()
{
    View::composer('layout', function ($view) {

        $folders = Cache::remember('folders_cache', 60, function () {

            $graph = app(MicrosoftGraphService::class);

            return $graph->folders()['value'] ?? [];

        });

        $view->with('folders', $folders);

    });
}
}
