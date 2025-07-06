<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class DatabaseErrorHandler
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (QueryException $e) {
            // Solo interceptar si es una petición de Filament
            if ($request->is('*/dashboard/*') || $request->wantsJson()) {
                return $this->handleDatabaseError($request, $e);
            }
            
            // Si no es Filament, dejar que el error se propague
            throw $e;
        }
    }

    /**
     * Manejar errores de base de datos
     */
    private function handleDatabaseError(Request $request, QueryException $e): Response
    {
        $message = $e->getMessage();
        $errors = [];

        // Log del error para debugging
        Log::error('DatabaseErrorHandler: ' . $message);

        // Detectar tipo de error
        if ($e->errorInfo[1] ?? null === 1062 || strpos($message, 'Duplicate entry') !== false) {
            // Error de clave duplicada
            if (strpos($message, 'personas_correo_unique') !== false || strpos($message, 'correo') !== false) {
                $errors['persona.correo'] = ['El correo electrónico ya existe en el sistema.'];
            } elseif (strpos($message, 'personas_dni_unique') !== false || strpos($message, 'DNI') !== false) {
                $errors['persona.DNI'] = ['El DNI ya existe en el sistema.'];
            } elseif (strpos($message, 'personas_celular_unique') !== false || strpos($message, 'celular') !== false) {
                $errors['persona.celular'] = ['El número de celular ya existe en el sistema.'];
            } else {
                $errors['general'] = ['Ya existe un registro con estos datos en el sistema.'];
            }
        } else {
            // Otros errores de base de datos
            $errors['general'] = ['Error en los datos proporcionados. Verifique la información.'];
        }

        // Devolver respuesta JSON para Filament
        return response()->json([
            'message' => 'Los datos proporcionados no son válidos.',
            'errors' => $errors
        ], 422);
    }
}
