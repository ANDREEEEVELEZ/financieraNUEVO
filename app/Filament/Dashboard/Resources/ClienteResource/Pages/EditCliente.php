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

        // Prevent Asesores from reassigning a client to a different asesor via a crafted POST
        $user = auth()->user();
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Services\CacheService::getAsesorByUserId($user->id);
            if ($asesor) {
                $data['asesor_id'] = $asesor->id;
            }
        }

        return $data;
    }

    protected function fillForm(): void
    {
        $this->form->fill(array_merge(
            $this->record->toArray(),
            [
                'persona' => $this->record->persona->toArray(),
                'persona_id' => $this->record->persona->id  // Para validaciones de unicidad
            ]
        ));
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
            ->icon('heroicon-o-trash')
                ->action(function () {
                    // En lugar de eliminar, cambiar el estado a inactivo
                    $this->record->estado_cliente = 'Inactivo';
                    $this->record->save();

                    $this->redirect($this->getResource()::getUrl('index'));
                })
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
