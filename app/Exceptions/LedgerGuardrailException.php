<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class LedgerGuardrailException extends RuntimeException
{
    public function __construct(
        string $message = 'The database rejected the ledger posting.'
    ) {
        parent::__construct($message);
    }
}
