<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use App\Models\Token;

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
    public function boot(): void
    {
        View::composer('mail.layout', function ($view) {
            $user = Auth::user();

            // kalau belum login
            if (!$user) {
                $view->with([
                    'tokens' => collect(),
                    'active' => null,
                ]);
                return;
            }

            $cacheKey = $user->isSubUser()
                ? "tokens.sub_user.{$user->id}"
                : "tokens.owner.{$user->id}";

            $tokens = cache()->remember($cacheKey, 60, function () use ($user) {
                // owner: ambil semua token miliknya
                if (!$user->isSubUser()) {
                    return Token::where('user_id', $user->id)
                        ->orderByDesc('id')
                        ->get();
                }

                // sub-user: ambil token yang di-assign
                return $user->accessibleTokens()
                    ->orderByDesc('tokens.id')
                    ->get();
            });

            $activeTokenId = session('active_token');

            $active = null;

            if ($activeTokenId) {
                $active = $tokens->firstWhere('id', $activeTokenId);
            }

            if (!$active) {
                $active = $tokens->first();
            }

            $view->with([
                'tokens' => $tokens,
                'active' => $active,
            ]);
        });
    }
}