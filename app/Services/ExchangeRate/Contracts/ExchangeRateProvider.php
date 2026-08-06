<?php

declare(strict_types=1);

namespace App\Services\ExchangeRate\Contracts;

use App\Data\ExchangeRate\ExchangeRateData;
use App\Enums\Currency;

interface ExchangeRateProvider
{
    public function fetch(
        Currency $from,
        Currency $to
    ): ExchangeRateData;
}
