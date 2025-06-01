<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && !auth()->user()->active) {
            auth()->logout();
            
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Tu cuenta está inactiva.'], 403);
            }
            
            return redirect()->route('filament.auth.login')->with('error', 'Tu cuenta está inactiva. Por favor, contacta al administrador.');
        }

        return $next($request);
    }
}
