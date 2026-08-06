<?php

declare(strict_types=1);

namespace App\Enums;

enum SettlementStatus: string
{
    case Initiated = 'initiated';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function rank(): int
    {
        return match ($this) {
            self::Initiated => 10,
            self::Processing => 20,
            self::Completed,
            self::Failed => 30,
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed,
            self::Failed => true,

            default => false,
        };
    }
}
