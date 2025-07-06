<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // Interceptar errores de base de datos relacionados con duplicados
        if ($exception instanceof QueryException) {
            $errorCode = $exception->errorInfo[1] ?? null;
            $errorMessage = $exception->getMessage();
            
            // Error de clave duplicada (1062)
            if ($errorCode === 1062) {
                return $this->handleDuplicateKeyError($request, $exception);
            }
            
            // Error de constraint violation (23000)
            if (strpos($errorMessage, 'SQLSTATE[23000]') !== false) {
                return $this->handleConstraintViolationError($request, $exception);
            }
        }
        
        return parent::render($request, $exception);
    }

    /**
     * Manejar errores de clave duplicada
     */
    private function handleDuplicateKeyError(Request $request, QueryException $exception)
    {
        $message = $exception->getMessage();
        $errors = [];
        
        // Detectar qué campo está duplicado
        if (strpos($message, 'personas_correo_unique') !== false || 
            strpos($message, 'correo') !== false) {
            $errors['persona.correo'] = ['El correo electrónico ya existe en el sistema.'];
        } elseif (strpos($message, 'personas_dni_unique') !== false || 
                  strpos($message, 'DNI') !== false) {
            $errors['persona.DNI'] = ['El DNI ya existe en el sistema.'];
        } elseif (strpos($message, 'personas_celular_unique') !== false || 
                  strpos($message, 'celular') !== false) {
            $errors['persona.celular'] = ['El número de celular ya existe en el sistema.'];
        } else {
            // Error genérico si no podemos determinar el campo
            $errors['general'] = ['Ya existe un registro con estos datos en el sistema.'];
        }
        
        // Log del error para debugging
        Log::error('Error de duplicado interceptado: ' . $message);
        
        // Crear excepción de validación
        $validationException = ValidationException::withMessages($errors);
        
        // Si es una petición AJAX/JSON (como Filament), devolver JSON
        if ($request->wantsJson() || $request->is('*/dashboard/*')) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $errors
            ], 422);
        }
        
        // Para peticiones web normales, redirigir con errores
        return redirect()->back()
            ->withInput($request->input())
            ->withErrors($errors);
    }

    /**
     * Manejar errores de constraint violation
     */
    private function handleConstraintViolationError(Request $request, QueryException $exception)
    {
        $message = $exception->getMessage();
        $errors = [];
        
        // Detectar el tipo de constraint violation
        if (strpos($message, 'Duplicate entry') !== false) {
            if (strpos($message, 'correo') !== false) {
                $errors['persona.correo'] = ['El correo electrónico ya existe en el sistema.'];
            } elseif (strpos($message, 'DNI') !== false) {
                $errors['persona.DNI'] = ['El DNI ya existe en el sistema.'];
            } elseif (strpos($message, 'celular') !== false) {
                $errors['persona.celular'] = ['El número de celular ya existe en el sistema.'];
            } else {
                $errors['general'] = ['Ya existe un registro con estos datos en el sistema.'];
            }
        } else {
            $errors['general'] = ['Error en los datos proporcionados. Verifique la información.'];
        }
        
        // Log del error para debugging
        Log::error('Error de constraint violation interceptado: ' . $message);
        
        // Si es una petición AJAX/JSON (como Filament), devolver JSON
        if ($request->wantsJson() || $request->is('*/dashboard/*')) {
            return response()->json([
                'message' => 'Los datos proporcionados no son válidos.',
                'errors' => $errors
            ], 422);
        }
        
        // Para peticiones web normales, redirigir con errores
        return redirect()->back()
            ->withInput($request->input())
            ->withErrors($errors);
    }
}
