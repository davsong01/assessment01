<?php

declare(strict_types=1);

namespace App\Services\Swap;

use App\Data\Swap\SwapQuoteData;
use InvalidArgumentException;

final class SwapQuoteCalculator
{
    private const CALCULATION_SCALE = 18;

    private const BASIS_POINTS_DIVISOR = 10_000;

    private const SLIPPAGE_THRESHOLD_SUBUNITS = 100_000_000;

    private const SLIPPAGE_TIER_SIZE_SUBUNITS = 50_000_000;

    private const BASE_SPREAD_BASIS_POINTS = 50;

    private const ADDITIONAL_SPREAD_BASIS_POINTS = 10;

    public function calculate(
        int $sourceAmountSubunits,
        string $marketRate
    ): SwapQuoteData {
        if ($sourceAmountSubunits < 1) {
            throw new InvalidArgumentException(
                'The source amount must be greater than zero.'
            );
        }

        if (! is_numeric($marketRate)) {
            throw new InvalidArgumentException(
                'The market rate must be a numeric string.'
            );
        }

        if (bccomp($marketRate, '0', self::CALCULATION_SCALE) !== 1) {
            throw new InvalidArgumentException(
                'The market rate must be greater than zero.'
            );
        }

        $grossDecimal = bcmul(
            (string) $sourceAmountSubunits,
            $marketRate,
            self::CALCULATION_SCALE
        );

        $spreadBasisPoints = $this->spreadBasisPoints(
            $sourceAmountSubunits
        );

        $spreadDecimal = $spreadBasisPoints === 0
            ? '0'
            : bcdiv(
                bcmul(
                    $grossDecimal,
                    (string) $spreadBasisPoints,
                    self::CALCULATION_SCALE
                ),
                (string) self::BASIS_POINTS_DIVISOR,
                self::CALCULATION_SCALE
            );

        $destinationDecimal = bcsub(
            $grossDecimal,
            $spreadDecimal,
            self::CALCULATION_SCALE
        );

        $grossDestinationSubunits = $this->roundHalfEven(
            $grossDecimal
        );

        $destinationAmountSubunits = $this->roundHalfEven(
            $destinationDecimal
        );

        return new SwapQuoteData(
            sourceAmountSubunits: $sourceAmountSubunits,
            marketRate: $marketRate,
            spreadBasisPoints: $spreadBasisPoints,
            grossDestinationSubunits: $grossDestinationSubunits,
            spreadAmountSubunits: (
                $grossDestinationSubunits
                - $destinationAmountSubunits
            ),
            destinationAmountSubunits: $destinationAmountSubunits
        );
    }

    private function spreadBasisPoints(
        int $sourceAmountSubunits
    ): int {
        if (
            $sourceAmountSubunits
            <= self::SLIPPAGE_THRESHOLD_SUBUNITS
        ) {
            return 0;
        }

        $amountAboveThreshold = (
            $sourceAmountSubunits
            - self::SLIPPAGE_THRESHOLD_SUBUNITS
        );

        $additionalTiers = intdiv(
            $amountAboveThreshold
                + self::SLIPPAGE_TIER_SIZE_SUBUNITS
                - 1,
            self::SLIPPAGE_TIER_SIZE_SUBUNITS
        );

        return self::BASE_SPREAD_BASIS_POINTS
            + (
                $additionalTiers
                * self::ADDITIONAL_SPREAD_BASIS_POINTS
            );
    }

    /**
     * Applies ROUND_HALF_EVEN (Banker's Rounding) to a BCMath decimal.
     */
    private function roundHalfEven(string $value): int
    {
        $negative = str_starts_with($value, '-');

        if ($negative) {
            $value = substr($value, 1);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );

        if (! is_numeric($whole)) {
            throw new InvalidArgumentException(
                'Invalid numeric value.'
            );
        }

        $wholeInt = (int) $whole;

        $firstFractionDigit = (int) ($fraction[0] ?? '0');
        $remainingFraction = substr($fraction, 1);

        $roundUp = false;

        if ($firstFractionDigit > 5) {
            $roundUp = true;
        } elseif ($firstFractionDigit === 5) {
            $hasRemainingDigits = trim(
                $remainingFraction,
                '0'
            ) !== '';

            $roundUp = $hasRemainingDigits
                || ($wholeInt % 2 !== 0);
        }

        if ($roundUp) {
            $wholeInt++;
        }

        return $negative
            ? -$wholeInt
            : $wholeInt;
    }
}
