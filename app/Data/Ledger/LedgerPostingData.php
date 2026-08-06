<?php

declare(strict_types=1);

namespace App\Data\Ledger;

use App\Enums\LedgerTransactionType;

final readonly class LedgerPostingData
{
    /**
     * @param  list<LedgerEntryData>  $entries
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $reference,
        public LedgerTransactionType $type,
        public array $entries,
        public array $metadata = []
    ) {}
}
