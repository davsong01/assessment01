<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidWalletSelectionException extends RuntimeException
{
    public function __construct(
        string $message = 'The selected wallets are invalid.'
    ) {
        parent::__construct($message);
    }
}
