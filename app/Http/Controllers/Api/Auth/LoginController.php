<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\TokenIssuerInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\Api\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct(private TokenIssuerInterface $tokenIssuer) {}

    /**
     * Authenticate the user and issue an access token + refresh token pair.
     *
     * Steps:
     * 1. Validate via LoginRequest (email, password, device_name).
     * 2. Validate credentials via the web guard, without touching session state
     *    (this endpoint issues stateless bearer tokens).
     * 3. Reject if user is inactive (mirror CheckUserActive logic).
     * 4. Delegate issuance to TokenIssuerInterface (IssueTokenPair) so both
     *    /auth/login and /v1/auth/login share the exact same contract as
     *    RefreshTokenController — see App\Domain\Auth\IssueTokenPair.
     *
     * `Auth::guard('web')->validate()` deliberately never touches session
     * state and — unlike `attempt()` — never fires `Illuminate\Auth\Events\
     * {Login,Failed}` on its own (verified against
     * `Illuminate\Auth\SessionGuard`, laravel-security-hardening Slice 6 /
     * design D8). Both events are fired explicitly below so the auth-event
     * listeners in `app/Listeners/Auth/` have something to react to; this
     * does not change the response contract or any existing behavior.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');
        $guard = Auth::guard('web');

        if (!$guard->validate($credentials)) {
            event(new Failed('web', $guard->getLastAttempted(), $credentials));

            return ApiResponse::error('Invalid credentials.', 401);
        }

        /** @var \App\Models\User $user */
        $user = User::where('email', $credentials['email'])->firstOrFail();

        if (!$user->active) {
            return ApiResponse::error('Account deactivated.', 403);
        }

        event(new Login('web', $user, false));

        $pair = ($this->tokenIssuer)($user, $request->device_name);

        return ApiResponse::success([
            ...$pair,
            'user' => new UserResource($user),
        ]);
    }
}
