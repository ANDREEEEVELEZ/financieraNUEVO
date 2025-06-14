<?php

namespace App\Filament\Dashboard\Resources\RetanqueoResource\Pages;

use App\Filament\Dashboard\Resources\RetanqueoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRetanqueos extends ListRecords
{
    protected static string $resource = RetanqueoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->icon('heroicon-o-plus-circle'),
        ];
    }
}
