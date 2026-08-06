<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ExchangeRateUnavailableException extends RuntimeException
{
    public function __construct(
        string $message = 'A safe exchange rate is currently unavailable.'
    ) {
        parent::__construct($message);
    }
}
