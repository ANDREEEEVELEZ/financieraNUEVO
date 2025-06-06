<?php

namespace App\Filament\Dashboard\Resources\IngresosResource\Pages;

use App\Filament\Dashboard\Resources\IngresosResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIngresos extends EditRecord
{
    protected static string $resource = IngresosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
