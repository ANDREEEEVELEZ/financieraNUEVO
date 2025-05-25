<?php

namespace App\Filament\Dashboard\Resources\AsesorResource\Pages;

use App\Filament\Dashboard\Resources\AsesorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Persona;
use App\Models\User;

class CreateAsesor extends CreateRecord
{
    // Definir correctamente la propiedad $resource
    protected static string $resource = AsesorResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
      // Crear la Persona primero
    $persona = Persona::create([
        'nombre' => $data['nombre'],
        'apellidos' => $data['apellidos'],
        'DNI' => $data['DNI'],
        'sexo' => $data['sexo'],
        'estado_civil' => $data['estado_civil'],
        'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
        'celular' => $data['celular'] ?? null,
        'correo' => $data['correo'] ?? null,
        'direccion' => $data['direccion'] ?? null,
        'distrito' => $data['distrito'] ?? null,
    ]);

    // Crear el Usuario con la Persona vinculada
    $userData = $data['user'] ?? null;
    if ($userData) {
        $user = User::create([
            'persona_id' => $persona->id,
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => bcrypt($userData['password']),
        ]);

        $data['user_id'] = $user->id;
    }

    $data['persona_id'] = $persona->id;

    // Elimina el arreglo user para evitar error de asignación masiva
    unset($data['user']);

    return $data;
    }
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
