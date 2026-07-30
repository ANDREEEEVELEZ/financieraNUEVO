<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Single authoritative source for issuing a Sanctum access token + RefreshToken
 * pair. Both LoginController and RefreshTokenController delegate here so the
 * response contract and TTL handling can never drift between the two routes.
 */
interface TokenIssuerInterface
{
    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function __invoke(User $user, string $deviceName): array;
}
