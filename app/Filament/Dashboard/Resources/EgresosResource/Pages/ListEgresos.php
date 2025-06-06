<?php

namespace App\Filament\Dashboard\Resources\EgresosResource\Pages;

use App\Filament\Dashboard\Resources\EgresosResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEgresos extends ListRecords
{
    protected static string $resource = EgresosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
