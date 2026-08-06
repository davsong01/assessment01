<?php

declare(strict_types=1);

namespace App\Services\ExchangeRate\Providers;

use App\Data\ExchangeRate\ExchangeRateData;
use App\Enums\Currency;
use App\Exceptions\ExchangeRateUnavailableException;
use App\Services\ExchangeRate\Contracts\ExchangeRateProvider;

final class MockExchangeRateProvider implements ExchangeRateProvider
{
    public function fetch(
        Currency $from,
        Currency $to
    ): ExchangeRateData {
        $rate = match (true) {
            $from === Currency::NGN
                && $to === Currency::CNY => '0.004750',

            $from === Currency::CNY
                && $to === Currency::NGN => '210.526316',

            $from === $to => '1',

            default => throw new ExchangeRateUnavailableException(
                sprintf(
                    'The mock exchange-rate provider does not support %s/%s.',
                    $from->value,
                    $to->value
                )
            ),
        };

        return new ExchangeRateData(
            from: $from,
            to: $to,
            rate: $rate,
            fetchedAt: now()->getTimestamp()
        );
    }
}
