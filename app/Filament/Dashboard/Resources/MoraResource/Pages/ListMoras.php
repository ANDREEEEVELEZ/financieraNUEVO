<?php

namespace App\Filament\Dashboard\Resources\MoraResource\Pages;

use App\Filament\Dashboard\Resources\MoraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMoras extends ListRecords
{
    protected static string $resource = MoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
