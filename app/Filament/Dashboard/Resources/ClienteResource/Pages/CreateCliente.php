<?php

namespace App\Filament\Dashboard\Resources\ClienteResource\Pages;

use App\Filament\Dashboard\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Persona;

class CreateCliente extends CreateRecord
{
    public static string $resource = ClienteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = request()->user();

        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();

            if (!$asesor) {
                throw new \Illuminate\Validation\ValidationException(
                    \Illuminate\Support\Facades\Validator::make([], [
                        'asesor_id' => 'El usuario autenticado no tiene un asesor asociado.',
                    ])
                );
            }

            $data['asesor_id'] = $asesor->id; // Asignar el ID del asesor desde la tabla `asesores`
        } else if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones'])) {
            // Validar que el asesor seleccionado exista y esté activo
            $asesor = isset($data['asesor_id']) ? \App\Models\Asesor::where('id', $data['asesor_id'])->where('estado_asesor', 'Activo')->first() : null;
            if (!$asesor) {
                throw new \Illuminate\Validation\ValidationException(
                    \Illuminate\Support\Facades\Validator::make([], [
                        'asesor_id' => 'Debe seleccionar un asesor válido y activo.',
                    ])
                );
            }
        }

        $existingPersona = Persona::where('DNI', $data['persona']['DNI'])->first();

        if ($existingPersona) {
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Support\Facades\Validator::make([], [
                    'DNI' => 'El DNI ya está registrado en el sistema.',
                ])
            );
        }

        $persona = Persona::create($data['persona']);
        $data['persona_id'] = $persona->id;
        unset($data['persona']);
        $data['estado_cliente'] = 'Activo'; // Forzar siempre Activo

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
