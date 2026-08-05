<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class TotpNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Two-factor authentication is not configured for this account.'
        );
    }
}
