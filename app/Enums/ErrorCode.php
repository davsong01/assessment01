<?php

declare(strict_types=1);

namespace App\Enums;

enum ErrorCode: string
{
    case Validation = 'VALIDATION_ERROR';

    case Unauthorized = 'UNAUTHORIZED';

    case Forbidden = 'FORBIDDEN';

    case NotFound = 'NOT_FOUND';

    case Conflict = 'CONFLICT';

    case Unprocessable = 'UNPROCESSABLE_ENTITY';

    case TooManyRequests = 'TOO_MANY_REQUESTS';

    case ServerError = 'SERVER_ERROR';

    case InvalidAction = 'INVALID_ACTION_PAYLOAD';

    case TwoFactorAuthentication = 'TWO_FACTOR_AUTHENTICATION_ERROR';
}
