<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\AuditServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ResetPasswordRequest;
use App\Http\Responses\ApiResponse;
use App\Models\RefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordController extends Controller
{
    public function __construct(private AuditServiceInterface $auditService) {}

    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();

                // Invalidate every existing session for this user — a
                // password reset must kill all previously-issued tokens.
                $user->tokens()->delete();
                RefreshToken::active()->where('user_id', $user->id)->update(['revoked_at' => now()]);

                $this->auditService->registrar('password_reset', $user, [], [], 'Password reset via API');
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error('Invalid or expired reset token.', 422);
        }

        return ApiResponse::success(null, 'Password has been reset successfully.');
    }
}
