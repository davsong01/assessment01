<?php

declare(strict_types=1);

use App\Enums\Currency;
use App\Exceptions\ExchangeRateUnavailableException;
use App\Services\ExchangeRate\Providers\HttpExchangeRateProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('fetches a valid exchange rate from the HTTP provider', function (): void {
    config()->set(
        'services.exchange_rate.endpoint',
        'https://mock-rate.test/rates'
    );

    config()->set(
        'services.exchange_rate.timeout_seconds',
        3
    );

    Http::fake([
        'https://mock-rate.test/rates*' => Http::response([
            'from' => 'NGN',
            'to' => 'CNY',
            'rate' => '0.004750',
        ]),
    ]);

    $provider = app(HttpExchangeRateProvider::class);

    $rate = $provider->fetch(
        Currency::NGN,
        Currency::CNY
    );

    expect($rate->from)
        ->toBe(Currency::NGN)
        ->and($rate->to)
        ->toBe(Currency::CNY)
        ->and($rate->rate)
        ->toBe('0.004750');

    Http::assertSent(
        function (Request $request): bool {
            return str_starts_with(
                $request->url(),
                'https://mock-rate.test/rates'
            )
                && $request['from'] === 'NGN'
                && $request['to'] === 'CNY';
        }
    );
});

it('rejects an invalid HTTP provider response', function (): void {
    config()->set(
        'services.exchange_rate.endpoint',
        'https://mock-rate.test/rates'
    );

    Http::fake([
        'https://mock-rate.test/rates*' => Http::response([
            'from' => 'NGN',
            'to' => 'CNY',
            'rate' => 0.004750,
        ]),
    ]);

    $provider = app(HttpExchangeRateProvider::class);

    expect(
        fn () => $provider->fetch(
            Currency::NGN,
            Currency::CNY
        )
    )->toThrow(ExchangeRateUnavailableException::class);
});

it('rejects an unsuccessful HTTP provider response', function (): void {
    config()->set(
        'services.exchange_rate.endpoint',
        'https://mock-rate.test/rates'
    );

    Http::fake([
        'https://mock-rate.test/rates*' => Http::response(
            ['message' => 'Unavailable'],
            503
        ),
    ]);

    $provider = app(HttpExchangeRateProvider::class);

    expect(
        fn () => $provider->fetch(
            Currency::NGN,
            Currency::CNY
        )
    )->toThrow(ExchangeRateUnavailableException::class);
});
