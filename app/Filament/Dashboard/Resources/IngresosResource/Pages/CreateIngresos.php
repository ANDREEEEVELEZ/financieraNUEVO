<?php

namespace App\Filament\Dashboard\Resources\IngresosResource\Pages;

use App\Filament\Dashboard\Resources\IngresosResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateIngresos extends CreateRecord
{
    protected static string $resource = IngresosResource::class;

    /**
     * Redireccionar a la lista después de crear
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
