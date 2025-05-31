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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = request()->user();

        if ($user->hasRole('Asesor')) {
            $data['asesor_id'] = $user->id;
        }

        return $data;
    }
}
