<?php

namespace App\Filament\Dashboard\Resources\PersonaResource\Pages;

use App\Filament\Dashboard\Resources\PersonaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPersonas extends ListRecords
{
    protected static string $resource = PersonaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
