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

    case LedgerImbalance = 'LEDGER_IMBALANCE';

    case InvalidLedgerEntry = 'INVALID_LEDGER_ENTRY';

    case InsufficientBalance = 'INSUFFICIENT_BALANCE';

    case DuplicateTransaction = 'DUPLICATE_TRANSACTION';

    case LedgerGuardrail = 'LEDGER_GUARDRAIL_REJECTION';

    case ExchangeRateUnavailable = 'EXCHANGE_RATE_UNAVAILABLE';
}
