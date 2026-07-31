<?php

namespace App\Domain\Auth;

use App\Contracts\TokenIssuerInterface;
use App\Models\RefreshToken;
use App\Models\User;

/**
 * Issues a Sanctum access token (with an explicit per-token expiry) plus a
 * RefreshToken for the same user/device.
 *
 * The access token's expiry is passed explicitly to createToken() rather than
 * relying on config('sanctum.expiration') — that global setting is nulled
 * (D1) precisely so per-token TTLs can be honored, which means expires_in
 * here is always derived from config('api_auth.access_token_ttl_minutes'),
 * never from config('sanctum.expiration') * 60 (that expression would
 * silently evaluate to 0 once expiration is null — the bug Scenario 1.1.d
 * guards against).
 *
 * RefreshToken::issueFor() is reused unchanged: it is already correct
 * (SHA-256 at rest, plaintext returned once, TTL from
 * config('api_auth.refresh_token_ttl_days')).
 */
class IssueTokenPair implements TokenIssuerInterface
{
    public function __invoke(User $user, string $deviceName): array
    {
        $ttlMinutes = (int) config('api_auth.access_token_ttl_minutes');
        $expiresAt = now()->addMinutes($ttlMinutes);

        $newAccessToken = $user->createToken($deviceName, ['*'], $expiresAt);

        $refresh = RefreshToken::issueFor($user, $deviceName, $newAccessToken->accessToken->id);

        return [
            'access_token'  => $newAccessToken->plainTextToken,
            'refresh_token' => $refresh['plaintext'],
            'token_type'    => 'Bearer',
            'expires_in'    => $ttlMinutes * 60,
        ];
    }
}
