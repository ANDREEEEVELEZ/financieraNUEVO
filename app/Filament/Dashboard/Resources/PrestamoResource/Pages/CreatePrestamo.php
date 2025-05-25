<?php

namespace App\Filament\Dashboard\Resources\PrestamoResource\Pages;

use App\Filament\Dashboard\Resources\PrestamoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePrestamo extends CreateRecord
{
    protected static string $resource = PrestamoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['estado'] = 'Pendiente';
        return $data;
    }
    
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
