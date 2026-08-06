<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Contracts\Security\ElevatedActionTokenStore;
use App\Data\Security\ElevatedActionTokenData;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use JsonException;

final readonly class RedisElevatedActionTokenStore implements ElevatedActionTokenStore
{
    private const KEY_PREFIX = 'eat:';

    public function __construct(
        private RedisFactory $redis
    ) {}

    public function put(
        ElevatedActionTokenData $token,
        int $ttlSeconds
    ): void {
        $payload = json_encode(
            $token->toArray(),
            JSON_THROW_ON_ERROR
        );

        $this->redis
            ->connection()
            ->setex(
                $this->key($token->tokenId),
                $ttlSeconds,
                $payload
            );
    }

    // public function consume(
    //     string $tokenId
    // ): ?ElevatedActionTokenData {
    //     $value = $this->redis
    //         ->connection()
    //         ->command('GETDEL', [
    //             $this->key($tokenId),
    //         ]);

    //     if (! is_string($value) || $value === '') {
    //         return null;
    //     }

    //     try {
    //         $decoded = json_decode(
    //             $value,
    //             true,
    //             512,
    //             JSON_THROW_ON_ERROR
    //         );
    //     } catch (JsonException) {
    //         return null;
    //     }

    //     if (! is_array($decoded)) {
    //         return null;
    //     }

    //     return ElevatedActionTokenData::fromArray([
    //         'token_id' => (string) $decoded['token_id'],
    //         'user_id' => (int) $decoded['user_id'],
    //         'action' => (string) $decoded['action'],
    //         'action_hash' => (string) $decoded['action_hash'],
    //         'issued_at' => (int) $decoded['issued_at'],
    //         'expires_at' => (int) $decoded['expires_at'],
    //     ]);
    // }
    // with LUA fallback for GETDEL command in case the Redis version does not support it
    public function consume(
        string $tokenId
    ): ?ElevatedActionTokenData {
        $connection = $this->redis->connection();
        $key = $this->key($tokenId);

        try {
            $value = $connection->command('GETDEL', [$key]);
        } catch (\Throwable) {
            $value = $connection->eval(
                <<<'LUA'
                local value = redis.call('GET', KEYS[1])

                if value then
                    redis.call('DEL', KEYS[1])
                end

                return value
                LUA,
                [$key],
                1
            );
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $decoded = json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        if (
            ! isset(
                $decoded['token_id'],
                $decoded['user_id'],
                $decoded['action'],
                $decoded['action_hash'],
                $decoded['issued_at'],
                $decoded['expires_at']
            )
        ) {
            return null;
        }

        return ElevatedActionTokenData::fromArray([
            'token_id' => (string) $decoded['token_id'],
            'user_id' => (int) $decoded['user_id'],
            'action' => (string) $decoded['action'],
            'action_hash' => (string) $decoded['action_hash'],
            'issued_at' => (int) $decoded['issued_at'],
            'expires_at' => (int) $decoded['expires_at'],
        ]);
    }

    private function key(string $tokenId): string
    {
        return self::KEY_PREFIX.$tokenId;
    }
}
