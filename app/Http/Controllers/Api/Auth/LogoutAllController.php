<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\AuditServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\RefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutAllController extends Controller
{
    public function __construct(private AuditServiceInterface $auditService) {}

    /**
     * Revoke every Sanctum access token and refresh token belonging to the
     * authenticated user — a remote "kill all sessions" action, useful when
     * a device is lost or stolen.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->tokens()->delete();

        RefreshToken::active()->where('user_id', $user->id)->update(['revoked_at' => now()]);

        $this->auditService->registrar('logout_all_sessions', $user, [], [], 'User-initiated revoke of all sessions');

        return ApiResponse::success(null, 'All sessions revoked.');
    }
}
