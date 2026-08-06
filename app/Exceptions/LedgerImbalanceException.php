<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class LedgerImbalanceException extends RuntimeException
{
    public function __construct(
        string $message = 'The ledger posting is not balanced.'
    ) {
        parent::__construct($message);
    }
}
