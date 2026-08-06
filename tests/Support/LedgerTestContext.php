<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerService;

final readonly class LedgerTestContext
{
    public function __construct(
        public LedgerService $ledgerService,
        public User $user,
        public Wallet $userNgnWallet,
        public Wallet $ngnClearingWallet,
        public Wallet $userCnyWallet,
    ) {}
}
