<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\Api\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Authenticate the user and issue a Sanctum bearer token.
     *
     * Steps:
     * 1. Validate via LoginRequest (email, password, device_name).
     * 2. Attempt credentials via Auth::attempt.
     * 3. Reject if user is inactive (mirror CheckUserActive logic).
     * 4. Issue token and return envelope.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return ApiResponse::error('Invalid credentials.', 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->active) {
            Auth::logout();

            return ApiResponse::error('Account deactivated.', 403);
        }

        $token = $user->createToken($request->device_name)->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
            'user'  => new UserResource($user),
        ]);
    }
}
