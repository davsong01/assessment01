<?php

declare(strict_types=1);

namespace App\Contracts\Security;

use App\Data\Security\ElevatedActionTokenData;

interface ElevatedActionTokenStore
{
    public function put(
        ElevatedActionTokenData $token,
        int $ttlSeconds
    ): void;

    public function consume(
        string $tokenId
    ): ?ElevatedActionTokenData;
}
