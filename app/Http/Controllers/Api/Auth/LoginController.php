<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\Api\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
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
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::guard('web')->validate($credentials)) {
            return ApiResponse::error('Invalid credentials.', 401);
        }

        /** @var \App\Models\User $user */
        $user = User::where('email', $credentials['email'])->firstOrFail();

        if (!$user->active) {
            return ApiResponse::error('Account deactivated.', 403);
        }

        $token = $user->createToken($request->device_name)->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user'  => new UserResource($user),
        ]);
    }
}
