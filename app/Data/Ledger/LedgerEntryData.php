<?php

declare(strict_types=1);

namespace App\Data\Ledger;

use App\Enums\Currency;
use App\Enums\LedgerEntryType;

final readonly class LedgerEntryData
{
    public function __construct(
        public string $walletId,
        public Currency $currency,
        public LedgerEntryType $entryType,
        public int $amountSubunits
    ) {}

    /**
     * @return array{
     *     wallet_id: string,
     *     currency: string,
     *     entry_type: string,
     *     amount_subunits: int
     * }
     */
    public function toArray(): array
    {
        return [
            'wallet_id' => $this->walletId,
            'currency' => $this->currency->value,
            'entry_type' => $this->entryType->value,
            'amount_subunits' => $this->amountSubunits,
        ];
    }
}
