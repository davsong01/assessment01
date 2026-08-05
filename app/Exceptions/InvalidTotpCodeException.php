<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class InvalidTotpCodeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The provided authentication code is invalid.');
    }
}
