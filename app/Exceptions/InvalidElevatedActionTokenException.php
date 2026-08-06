<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidElevatedActionTokenException extends RuntimeException
{
    public function __construct(
        string $message = 'The elevated action token is invalid or expired.'
    ) {
        parent::__construct($message);
    }
}
