<?php

namespace App\Filament\Dashboard\Resources\PagoResource\Pages;

use App\Filament\Dashboard\Resources\PagoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePago extends CreateRecord
{
    protected static string $resource = PagoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Solo establecer 'Pendiente' si no viene del formulario
        if (empty($data['estado_pago'])) {
            $data['estado_pago'] = 'Pendiente';
        }
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
