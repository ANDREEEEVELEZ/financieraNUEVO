<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Asesor;
use Illuminate\Support\Facades\Log;
/**
 * Middleware para verificar si el usuario está activo.
 */

class CheckUserActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Agregar logs de depuración
        Log::debug('CheckUserActive: Path = ' . $request->path());
        Log::debug('CheckUserActive: Auth check = ' . (Auth::check() ? 'true' : 'false'));
        
        if (Auth::check()) {
            $user = Auth::user();
            Log::debug('CheckUserActive: User ID = ' . $user->id);
            Log::debug('CheckUserActive: User Active = ' . ($user->active ? 'true' : 'false'));
            Log::debug('CheckUserActive: Roles = ' . implode(', ', $user->getRoleNames()->toArray()));
            
            // Comentado temporalmente para depuración
           
            // Verificar si el usuario tiene rol de asesor y está inactivo
            if ($user->hasRole('Asesor')) {
                $asesor = Asesor::where('user_id', $user->id)->first();
                if ($asesor && $asesor->estado_asesor === 'Inactivo') {
                    Auth::logout();
                    
                    if ($request->expectsJson()) {
                        return response()->json(['message' => '¡ASESOR INACTIVO! No tienes permiso para acceder al sistema. Por favor, contacta al administrador.'], 403);
                    }
                    
                    return redirect('/dashboard/login')
                        ->with('notification', [
                            'title' => '¡ASESOR INACTIVO!',
                            'message' => 'No tienes permiso para acceder al sistema. Por favor, contacta al administrador.',
                            'status' => 'danger',
                        ]);
                }
            }
            
            // Verificar si la cuenta está inactiva en general
            if (!$user->active) {
                 Auth::logout();
                
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Tu cuenta está inactiva.'], 403);
                }
                
                return redirect('/dashboard/login')
                    ->with('notification', [
                        'title' => 'Cuenta Inactiva',
                        'message' => 'Tu cuenta está inactiva. Por favor, contacta al administrador.',
                        'status' => 'danger',
                    ]);
            }
            
        }

        // Siempre permitir el paso durante la depuración
        return $next($request);
    }
}