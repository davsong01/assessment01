<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidLedgerEntryException extends RuntimeException
{
    public function __construct(
        string $message = 'One or more ledger entries are invalid.'
    ) {
        parent::__construct($message);
    }
}
