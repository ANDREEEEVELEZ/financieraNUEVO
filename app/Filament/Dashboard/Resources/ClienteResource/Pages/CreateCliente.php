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
