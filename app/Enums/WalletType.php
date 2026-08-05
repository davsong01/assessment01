<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletType: string
{
    case User = 'user';
    case Clearing = 'clearing';
    case Settlement = 'settlement';
    case Revenue = 'revenue';
}
