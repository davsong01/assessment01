<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

final class InvalidActionPayloadException extends InvalidArgumentException
{
    public function __construct(
        string $message = 'The action payload is invalid.'
    ) {
        parent::__construct($message);
    }
}
