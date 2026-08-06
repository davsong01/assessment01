<?php

declare(strict_types=1);

namespace App\Data\ExchangeRate;

use App\Enums\Currency;
use InvalidArgumentException;

final readonly class ExchangeRateData
{
    public function __construct(
        public Currency $from,
        public Currency $to,
        public string $rate,
        public int $fetchedAt
    ) {
        if (! self::isValidDecimal($rate)) {
            throw new InvalidArgumentException(
                'The exchange rate must be a positive decimal string.'
            );
        }
    }

    /**
     * @return array{
     *     from: string,
     *     to: string,
     *     rate: string,
     *     fetched_at: int
     * }
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->value,
            'to' => $this->to->value,
            'rate' => $this->rate,
            'fetched_at' => $this->fetchedAt,
        ];
    }

    /**
     * @param array{
     *     from: string,
     *     to: string,
     *     rate: string,
     *     fetched_at: int
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            from: Currency::from($data['from']),
            to: Currency::from($data['to']),
            rate: $data['rate'],
            fetchedAt: $data['fetched_at']
        );
    }

    private static function isValidDecimal(string $value): bool
    {
        if (
            preg_match(
                '/^(?:0|[1-9]\d*)(?:\.\d+)?$/',
                $value
            ) !== 1
        ) {
            return false;
        }

        if (! is_numeric($value)) {
            return false;
        }

        return bccomp($value, '0', 18) === 1;
    }
}
