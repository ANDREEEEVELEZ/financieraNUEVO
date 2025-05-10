<?php

namespace App\Filament\Dashboard\Resources\PrestamoResource\Pages;

use App\Filament\Dashboard\Resources\PrestamoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrestamos extends ListRecords
{
    protected static string $resource = PrestamoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
