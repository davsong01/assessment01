<?php

declare(strict_types=1);

namespace App\Services\ExchangeRate\Providers;

use App\Data\ExchangeRate\ExchangeRateData;
use App\Enums\Currency;
use App\Exceptions\ExchangeRateUnavailableException;
use App\Services\ExchangeRate\Contracts\ExchangeRateProvider;
use Illuminate\Support\Facades\Http;
use Throwable;

final class HttpExchangeRateProvider implements ExchangeRateProvider
{
    public function fetch(
        Currency $from,
        Currency $to
    ): ExchangeRateData {
        $endpoint = config('exchange-rate.endpoint');

        if (! is_string($endpoint) || $endpoint === '') {
            throw new ExchangeRateUnavailableException(
                'The exchange-rate provider is not configured.'
            );
        }

        $timeout = config(
            'exchange-rate.timeout_seconds',
            3
        );

        if (! is_int($timeout) || $timeout < 1) {
            $timeout = 3;
        }

        try {
            $response = Http::acceptJson()
                ->timeout($timeout)
                ->retry(
                    times: 2,
                    sleepMilliseconds: 100,
                    throw: false
                )
                ->get($endpoint, [
                    'from' => $from->value,
                    'to' => $to->value,
                ]);
        } catch (Throwable) {
            throw new ExchangeRateUnavailableException(
                'The exchange-rate provider could not be reached.'
            );
        }

        if (! $response->successful()) {
            throw new ExchangeRateUnavailableException;
        }

        $rate = $response->json('rate');
        $responseFrom = $response->json('from');
        $responseTo = $response->json('to');

        if (
            ! is_string($rate)
            || $responseFrom !== $from->value
            || $responseTo !== $to->value
        ) {
            throw new ExchangeRateUnavailableException(
                'The exchange-rate provider returned an invalid response.'
            );
        }

        try {
            return new ExchangeRateData(
                from: $from,
                to: $to,
                rate: $rate,
                fetchedAt: now()->getTimestamp()
            );
        } catch (Throwable) {
            throw new ExchangeRateUnavailableException(
                'The exchange-rate provider returned an invalid rate.'
            );
        }
    }
}
