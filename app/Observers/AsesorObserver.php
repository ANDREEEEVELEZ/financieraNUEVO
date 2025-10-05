<?php

namespace App\Observers;

use App\Models\Asesor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class AsesorObserver
{
    /**
     * Handle the Asesor "updated" event.
     * Este método se ejecuta después de que se actualice el asesor.
     */
    public function updated(Asesor $asesor): void
    {
        // Verificar si el estado del asesor cambió a INACTIVO
        if ($asesor->wasChanged('estado_asesor') && $asesor->estado_asesor === 'INACTIVO') {
            $user = $asesor->user;
            
            if ($user) {
                // Si el usuario actualmente autenticado es el mismo que se está desactivando
                if (Auth::check() && Auth::user()->id === $user->id) {
                    // Cerrar la sesión del usuario inmediatamente
                    Auth::logout();
                    
                    // Regenerar el token de sesión
                    Session::regenerateToken();
                    
                    // Invalidar la sesión actual
                    Session::invalidate();
                }
            }
        }
        
        // Verificar si hay cambios en la relación persona
        if ($asesor->persona && $asesor->persona->wasChanged('correo')) {
            // Si el email cambió, cerrar todas las sesiones activas del usuario
            $user = $asesor->user;
            
            if ($user) {
                // Si el usuario actualmente autenticado es el mismo que se está editando
                if (Auth::check() && Auth::user()->id === $user->id) {
                    // Cerrar la sesión del usuario
                    Auth::logout();
                    
                    // Regenerar el token de sesión
                    Session::regenerateToken();
                }
            }
        }
    }
}
