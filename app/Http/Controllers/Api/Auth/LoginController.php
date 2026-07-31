<?php

namespace App\Http\Controllers\Api\Auth;

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
    /**
     * Authenticate the user and issue a Sanctum bearer token.
     *
     * Steps:
     * 1. Validate via LoginRequest (email, password, device_name).
     * 2. Validate credentials via the web guard, without touching session state
     *    (this endpoint issues stateless bearer tokens).
     * 3. Reject if user is inactive (mirror CheckUserActive logic).
     * 4. Issue token and return envelope.
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

        $token = $user->createToken($request->device_name)->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user'  => new UserResource($user),
        ]);
    }
}
