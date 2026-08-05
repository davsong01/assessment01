<?php

declare(strict_types=1);

namespace App\Enums;

enum LedgerTransactionType: string
{
    case InitialFunding = 'initial_funding';
    case Swap = 'swap';
    case Settlement = 'settlement';
    case Fee = 'fee';
    case Reversal = 'reversal';
}
