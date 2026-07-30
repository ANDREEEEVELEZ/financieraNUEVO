<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\TokenIssuerInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RefreshTokenRequest;
use App\Http\Resources\Api\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\RefreshToken;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class RefreshTokenController extends Controller
{
    public function __construct(private TokenIssuerInterface $tokenIssuer) {}

    /**
     * Rotate an access token + refresh token pair.
     *
     * The incoming refresh token is looked up by its SHA-256 hash (plaintext
     * is never stored). If valid, the old refresh token is revoked and its
     * linked access token deleted, then a brand-new pair is issued via the
     * same TokenIssuerInterface (IssueTokenPair) LoginController uses, so
     * expires_in is always derived from config('api_auth.access_token_ttl_minutes')
     * — never from config('sanctum.expiration') * 60, which would silently
     * evaluate to 0 now that sanctum.expiration is null (D1). Rotation-on-use
     * means a stolen refresh token can only be replayed once before detection
     * (the legitimate device's next refresh attempt will fail, signalling
     * compromise).
     */
    public function __invoke(RefreshTokenRequest $request): JsonResponse
    {
        $hash = hash('sha256', $request->refresh_token);

        $refreshToken = RefreshToken::active()->where('token', $hash)->first();

        if (!$refreshToken) {
            return ApiResponse::error('Invalid or expired refresh token.', 401);
        }

        $user = $refreshToken->user;

        if (!$user || !$user->active) {
            return ApiResponse::error('Invalid or expired refresh token.', 401);
        }

        $refreshToken->revoked_at = now();
        $refreshToken->save();

        PersonalAccessToken::find($refreshToken->access_token_id)?->delete();

        $pair = ($this->tokenIssuer)($user, $refreshToken->device_name);

        return ApiResponse::success([
            ...$pair,
            'user' => new UserResource($user),
        ]);
    }
}
