<?php

namespace App\Providers;

use App\Contracts\Security\ElevatedActionTokenStore;
use App\Services\Security\RedisElevatedActionTokenStore;
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
        $this->app->singleton(
            Google2FA::class,
            static fn (): Google2FA => new Google2FA
        );

        $this->app->singleton(
            ElevatedActionTokenStore::class,
            RedisElevatedActionTokenStore::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

        RateLimiter::for(
            'two-factor-challenge',
            function (Request $request): Limit {
                $userId = $request->user()?->getAuthIdentifier();

                return Limit::perMinute(5)
                    ->by(
                        sprintf(
                            '%s|%s',
                            is_int($userId) || is_string($userId)
                                ? (string) $userId
                                : 'guest',
                            $request->ip()
                        )
                    );
            }
        );
    }
}
