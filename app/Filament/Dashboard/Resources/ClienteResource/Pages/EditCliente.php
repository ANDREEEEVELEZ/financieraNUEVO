<?php

namespace App\Filament\Dashboard\Resources\ClienteResource\Pages;

use App\Filament\Dashboard\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Persona;

class EditCliente extends EditRecord
{
    public static string $resource = ClienteResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->record->persona->update($data['persona']);
        unset($data['persona']);
        return $data;
    }

    protected function fillForm(): void
    {
        $this->form->fill(array_merge(
            $this->record->toArray(),
            ['persona' => $this->record->persona->toArray()]
        ));
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
