<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    /**
     * Revoke the current bearer token.
     *
     * Only the presenting token is deleted — other tokens for the same user
     * remain valid (multi-device support).
     *
     * Token deletion here never goes through a guard's `logout()` method, so
     * `Illuminate\Auth\Events\Logout` never fires on its own (laravel-security-
     * hardening Slice 6 / design D8). Fired explicitly below, guard name
     * `sanctum` (the guard actually protecting this route), so the auth-event
     * listener has something to react to.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $user?->currentAccessToken()?->delete();

        if ($user) {
            event(new Logout('sanctum', $user));
        }

        return ApiResponse::success(null, 'Logged out successfully.');
    }
}
