<?php

namespace App\Filament\Dashboard\Resources\EgresosResource\Pages;

use App\Filament\Dashboard\Resources\EgresosResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEgresos extends EditRecord
{
    protected static string $resource = EgresosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
