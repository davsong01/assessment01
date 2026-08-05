<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\InvalidActionPayloadException;
use JsonException;

final class CanonicalJson
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidActionPayloadException
     */
    public static function encode(array $payload): string
    {
        $normalized = self::normalize($payload);

        try {
            return json_encode(
                $normalized,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
            );
        } catch (JsonException $exception) {
            throw new InvalidActionPayloadException(
                'The action payload could not be encoded.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function hash(array $payload): string
    {
        return hash('sha256', self::encode($payload));
    }

    /**
     * @throws InvalidActionPayloadException
     */
    private static function normalize(mixed $value): mixed
    {
        if (is_float($value)) {
            throw new InvalidActionPayloadException(
                'Floating-point values are not allowed in action payloads.'
            );
        }

        if (is_resource($value)) {
            throw new InvalidActionPayloadException(
                'Resources are not allowed in action payloads.'
            );
        }

        if (is_object($value)) {
            throw new InvalidActionPayloadException(
                'Objects are not allowed in action payloads.'
            );
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                static fn (mixed $item): mixed => self::normalize($item),
                $value
            );
        }

        ksort($value, SORT_STRING);

        $normalized = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new InvalidActionPayloadException(
                    'Associative action-payload keys must be strings.'
                );
            }

            $normalized[$key] = self::normalize($item);
        }

        return $normalized;
    }
}
