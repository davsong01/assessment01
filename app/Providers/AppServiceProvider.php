<?php

namespace App\Providers;

use App\Contracts\Security\ElevatedActionTokenStore;
use App\Services\ExchangeRate\Contracts\ExchangeRateProvider;
use App\Services\ExchangeRate\Providers\HttpExchangeRateProvider;
use App\Services\ExchangeRate\Providers\MockExchangeRateProvider;
use App\Services\Security\RedisElevatedActionTokenStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;
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

        $this->app->singleton(
            ExchangeRateProvider::class,
            function (Application $app): ExchangeRateProvider {
                $driver = config(
                    'services.exchange_rate.driver',
                    'http'
                );

                if (! is_string($driver)) {
                    throw new InvalidArgumentException(
                        'Exchange rate provider configuration is invalid.'
                    );
                }

                return match ($driver) {
                    'mock' => $app->make(
                        MockExchangeRateProvider::class
                    ),
                    'http' => $app->make(
                        HttpExchangeRateProvider::class
                    ),
                    default => throw new InvalidArgumentException(
                        sprintf(
                            'Unsupported exchange rate provider [%s].',
                            $driver
                        )
                    ),
                };
            }
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
