<?php

namespace App\Filament\Dashboard\Resources\AsesorResource\Pages;

use App\Filament\Dashboard\Resources\AsesorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;
use App\Models\Persona;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CreateAsesor extends CreateRecord
{
    protected static string $resource = AsesorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
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
        if (isset($data['DNI'])) {
            $dniExists = Persona::where('DNI', trim($data['DNI']))->exists();
            if ($dniExists) {
                $errors['DNI'] = 'El DNI ya existe en el sistema.';
            }
        }

        // Validar correo único
        if (isset($data['correo'])) {
            $correoExists = Persona::whereRaw('LOWER(correo) = ?', [strtolower(trim($data['correo']))])->exists();
            if ($correoExists) {
                $errors['correo'] = 'El correo electrónico ya existe en el sistema.';
            }
        }

        // Validar celular único
        if (isset($data['celular'])) {
            $celularExists = Persona::where('celular', trim($data['celular']))->exists();
            if ($celularExists) {
                $errors['celular'] = 'El número de celular ya existe en el sistema.';
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
        Log::error('Error de base de datos en CreateAsesor: ' . $message);

        // Detectar tipo de error
        if (strpos($message, 'personas_correo_unique') !== false || strpos($message, 'correo') !== false) {
            $errors['correo'] = 'El correo electrónico ya existe en el sistema.';
        } elseif (strpos($message, 'personas_dni_unique') !== false || strpos($message, 'DNI') !== false) {
            $errors['DNI'] = 'El DNI ya existe en el sistema.';
        } elseif (strpos($message, 'personas_celular_unique') !== false || strpos($message, 'celular') !== false) {
            $errors['celular'] = 'El número de celular ya existe en el sistema.';
        } else {
            $errors['general'] = 'Error al crear el asesor. Verifique los datos ingresados.';
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

    protected function afterCreate(): void
    {
        // Asignar el rol de Asesor al usuario asociado
        if ($this->record && $this->record->user) {
            $role = Role::findByName('Asesor');
            $this->record->user->assignRole($role);
        }
    }
}
