<?php

namespace App\Filament\Dashboard\Resources\RetanqueoResource\Pages;

use App\Filament\Dashboard\Resources\RetanqueoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRetanqueo extends CreateRecord
{
    protected static string $resource = RetanqueoResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
