<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class DuplicateLedgerReferenceException extends RuntimeException
{
    public function __construct(
        string $message = 'A ledger transaction with this reference already exists.'
    ) {
        parent::__construct($message);
    }
}
