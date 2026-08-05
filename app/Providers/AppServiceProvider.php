<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Google2FA
        $this->app->singleton(
            Google2FA::class,
            static fn (): Google2FA => new Google2FA
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // configure rate limiting for login attempts
        RateLimiter::for(
            'login',
            function (Request $request): Limit {
                $email = Str::lower(
                    (string) $request->input('email')
                );

                return Limit::perMinute(5)
                    ->by($email.'|'.$request->ip());
            }
        );
    }
}
