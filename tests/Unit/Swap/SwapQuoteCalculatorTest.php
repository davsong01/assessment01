<?php

declare(strict_types=1);

use App\Services\Swap\SwapQuoteCalculator;
use InvalidArgumentException;

it('converts subunits without floating point math', function (): void {
    $calculator = new SwapQuoteCalculator;

    $quote = $calculator->calculate(
        sourceAmountSubunits: 10_000_000,
        marketRate: '0.004750'
    );

    expect($quote->spreadBasisPoints)
        ->toBe(0)
        ->and($quote->grossDestinationSubunits)
        ->toBe(47_500)
        ->and($quote->destinationAmountSubunits)
        ->toBe(47_500);
});

it('applies progressive spread above the threshold', function (): void {
    $calculator = new SwapQuoteCalculator;

    $quote = $calculator->calculate(
        sourceAmountSubunits: 150_000_000,
        marketRate: '0.004750'
    );

    expect($quote->spreadBasisPoints)
        ->toBe(60)
        ->and($quote->grossDestinationSubunits)
        ->toBe(712_500)
        ->and($quote->destinationAmountSubunits)
        ->toBe(708_225)
        ->and($quote->spreadAmountSubunits)
        ->toBe(4_275);
});

it('applies the next progressive tier', function (): void {
    $calculator = new SwapQuoteCalculator;

    $quote = $calculator->calculate(
        sourceAmountSubunits: 150_000_001,
        marketRate: '0.004750'
    );

    expect($quote->spreadBasisPoints)
        ->toBe(70);
});

it('uses bankers rounding', function (): void {
    $calculator = new SwapQuoteCalculator;

    $roundsToEven = $calculator->calculate(
        sourceAmountSubunits: 1,
        marketRate: '2.5'
    );

    $roundsToNextEven = $calculator->calculate(
        sourceAmountSubunits: 1,
        marketRate: '3.5'
    );

    expect($roundsToEven->destinationAmountSubunits)
        ->toBe(2)
        ->and($roundsToNextEven->destinationAmountSubunits)
        ->toBe(4);
});

it('rejects invalid amounts and rates', function (): void {
    $calculator = new SwapQuoteCalculator;

    expect(
        fn () => $calculator->calculate(
            sourceAmountSubunits: 0,
            marketRate: '0.004750'
        )
    )->toThrow(InvalidArgumentException::class);

    expect(
        fn () => $calculator->calculate(
            sourceAmountSubunits: 100_000,
            marketRate: 'invalid'
        )
    )->toThrow(InvalidArgumentException::class);
});
