<?php

declare(strict_types=1);

namespace App\Data\Swap;

final readonly class SwapQuoteData
{
    public function __construct(
        public int $sourceAmountSubunits,
        public string $marketRate,
        public int $spreadBasisPoints,
        public int $grossDestinationSubunits,
        public int $spreadAmountSubunits,
        public int $destinationAmountSubunits
    ) {}

    /**
     * @return array{
     *     source_amount_subunits: int,
     *     market_rate: string,
     *     spread_basis_points: int,
     *     gross_destination_subunits: int,
     *     spread_amount_subunits: int,
     *     destination_amount_subunits: int
     * }
     */
    public function toArray(): array
    {
        return [
            'source_amount_subunits' => $this->sourceAmountSubunits,
            'market_rate' => $this->marketRate,
            'spread_basis_points' => $this->spreadBasisPoints,
            'gross_destination_subunits' => $this->grossDestinationSubunits,
            'spread_amount_subunits' => $this->spreadAmountSubunits,
            'destination_amount_subunits' => $this->destinationAmountSubunits,
        ];
    }
}
