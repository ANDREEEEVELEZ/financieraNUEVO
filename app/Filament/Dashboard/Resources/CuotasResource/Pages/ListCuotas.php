<?php

namespace App\Filament\Dashboard\Resources\CuotasResource\Pages;

use App\Filament\Dashboard\Resources\CuotasResource;
use Filament\Resources\Pages\ListRecords;

class ListCuotas extends ListRecords
{
    protected static string $resource = CuotasResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
