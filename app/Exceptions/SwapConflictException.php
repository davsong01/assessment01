<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class SwapConflictException extends RuntimeException
{
    public function __construct(
        string $message = 'The swap could not acquire the required locks.'
    ) {
        parent::__construct($message);
    }
}
