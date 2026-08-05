<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
