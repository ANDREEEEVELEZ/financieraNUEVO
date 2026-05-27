<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify the authenticated user is active.
 *
 * Stateless-safe: session invalidation is only attempted when a session
 * is actually present (i.e., not on bearer-token / API requests).
 */
class CheckUserActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Check if the authenticated asesor is marked as inactive.
        $asesor = \App\Infrastructure\Cache\CacheService::getAsesorByUserId($user->id);
        if ($asesor && strtolower($asesor->estado_asesor) === 'inactivo') {
            return $this->deactivate(
                $request,
                '¡ASESOR INACTIVO!',
                'No tienes permiso para acceder al sistema. Por favor, contacta al administrador.'
            );
        }

        // Check if the user account itself is inactive.
        if (! $user->active) {
            return $this->deactivate(
                $request,
                'Cuenta Inactiva',
                'Tu cuenta está inactiva. Por favor, contacta al administrador.'
            );
        }

        return $next($request);
    }

    /**
     * Terminate the request for a deactivated account.
     *
     * Returns a JSON 403 for API / expectsJson requests.
     * For web requests, invalidates the session and redirects to login.
     */
    private function deactivate(Request $request, string $title, string $message): Response
    {
        if ($request->expectsJson()) {
            return ApiResponse::error('Account deactivated.', 403);
        }

        // Only invalidate the session when one exists (not on stateless token requests).
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect('/dashboard/login')
            ->with('notification', [
                'title'   => $title,
                'message' => $message,
                'status'  => 'danger',
            ]);
    }
}
