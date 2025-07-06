<?php

namespace App\Observers;

use App\Models\Persona;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class PersonaObserver
{
    /**
     * Handle the Persona "updating" event.
     */
    public function updating(Persona $persona): void
    {
        // Verificar si el email está siendo cambiado
        if ($persona->isDirty('correo')) {
            // Obtener el email original
            $originalEmail = $persona->getOriginal('correo');
            
            // Si el email cambió, verificar si esta persona está asociada a un asesor
            if ($originalEmail !== $persona->correo) {
                // Buscar si esta persona tiene un asesor asociado
                $asesor = $persona->asesor;
                
                if ($asesor && $asesor->user) {
                    // Si el usuario actualmente autenticado es el mismo que se está editando
                    if (Auth::check() && Auth::user()->id === $asesor->user->id) {
                        // Cerrar la sesión del usuario
                        Auth::logout();
                        
                        // Regenerar el token de sesión
                        Session::regenerateToken();
                    }
                }
            }
        }
    }
}
