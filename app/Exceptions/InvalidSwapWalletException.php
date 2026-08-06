<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidSwapWalletException extends RuntimeException
{
    public function __construct(
        string $message = 'The selected wallets cannot be used for this swap.'
    ) {
        parent::__construct($message);
    }
}
