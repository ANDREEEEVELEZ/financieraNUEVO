<?php

namespace App\Filament\Dashboard\Resources\ClienteResource\Pages;

use App\Filament\Dashboard\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Persona;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class CreateCliente extends CreateRecord
{
    public static string $resource = ClienteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            $user = request()->user();

            if ($user->hasRole('Asesor')) {
                $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();

                if (!$asesor) {
                    throw new ValidationException(
                        Validator::make([], [
                            'asesor_id' => 'El usuario autenticado no tiene un asesor asociado.',
                        ])
                    );
                }

                $data['asesor_id'] = $asesor->id;
            } else if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones'])) {
                $asesor = isset($data['asesor_id']) ? \App\Models\Asesor::where('id', $data['asesor_id'])->where('estado_asesor', 'Activo')->first() : null;
                if (!$asesor) {
                    throw new ValidationException(
                        Validator::make([], [
                            'asesor_id' => 'Debe seleccionar un asesor válido y activo.',
                        ])
                    );
                }
            }

            // Validar duplicados ANTES de crear
            $this->validateUniqueFields($data);

            return $data;
            
        } catch (QueryException $e) {
            // Interceptar errores de base de datos y convertirlos
            $this->handleDatabaseError($e);
            // Nunca debería llegar aquí, pero por si acaso
            return $data;
        }
    }

    /**
     * Validar campos únicos antes de crear
     */
    private function validateUniqueFields(array $data): void
    {
        $errors = [];

        // Validar DNI único
        if (isset($data['persona']['DNI'])) {
            $dniExists = Persona::where('DNI', trim($data['persona']['DNI']))->exists();
            if ($dniExists) {
                $errors['persona.DNI'] = 'El DNI ya existe en el sistema.';
            }
        }

        // Validar correo único
        if (isset($data['persona']['correo'])) {
            $correoExists = Persona::whereRaw('LOWER(correo) = ?', [strtolower(trim($data['persona']['correo']))])->exists();
            if ($correoExists) {
                $errors['persona.correo'] = 'El correo electrónico ya existe en el sistema.';
            }
        }

        // Validar celular único
        if (isset($data['persona']['celular'])) {
            $celularExists = Persona::where('celular', trim($data['persona']['celular']))->exists();
            if ($celularExists) {
                $errors['persona.celular'] = 'El número de celular ya existe en el sistema.';
            }
        }

        // Si hay errores, lanzar excepción de validación
        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Manejar errores de base de datos
     */
    private function handleDatabaseError(QueryException $e): void
    {
        $message = $e->getMessage();
        $errors = [];

        // Log del error para debugging
        Log::error('Error de base de datos en CreateCliente: ' . $message);

        // Detectar tipo de error
        if (strpos($message, 'personas_correo_unique') !== false || strpos($message, 'correo') !== false) {
            $errors['persona.correo'] = 'El correo electrónico ya existe en el sistema.';
        } elseif (strpos($message, 'personas_dni_unique') !== false || strpos($message, 'DNI') !== false) {
            $errors['persona.DNI'] = 'El DNI ya existe en el sistema.';
        } elseif (strpos($message, 'personas_celular_unique') !== false || strpos($message, 'celular') !== false) {
            $errors['persona.celular'] = 'El número de celular ya existe en el sistema.';
        } else {
            $errors['general'] = 'Error al crear el cliente. Verifique los datos ingresados.';
        }

        throw ValidationException::withMessages($errors);
    }

    /**
     * Manejar la creación con try-catch adicional
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (QueryException $e) {
            $this->handleDatabaseError($e);
            // Nunca debería llegar aquí, pero por si acaso
            throw $e;
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
