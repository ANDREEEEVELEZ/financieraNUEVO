<?php

namespace App\Filament\Dashboard\Resources\RetanqueoResource\Pages;

use App\Filament\Dashboard\Resources\RetanqueoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRetanqueo extends EditRecord
{
    protected static string $resource = RetanqueoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
