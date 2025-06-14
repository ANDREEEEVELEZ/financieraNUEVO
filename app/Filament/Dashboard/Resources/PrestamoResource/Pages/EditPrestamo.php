<?php

namespace App\Filament\Dashboard\Resources\PrestamoResource\Pages;

use App\Filament\Dashboard\Resources\PrestamoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPrestamo extends EditRecord
{
    protected static string $resource = PrestamoResource::class;

    /** @var string|null */
    protected $oldEstado = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldEstado = $this->record->estado;
        // Si el usuario es Jefe y seleccionó un nuevo rol, lo actualizamos
        if (isset($data['nuevo_rol']) && !empty($data['nuevo_rol'])) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user->hasAnyRole(['Jefe de operaciones', 'Jefe de creditos'])) {
                // Remover todos los roles actuales y asignar el nuevo
                $user->syncRoles([$data['nuevo_rol']]);
            }
        }
        return parent::mutateFormDataBeforeSave($data);
    }

    protected function onSaved(): void
    {
        $prestamo = $this->record->fresh();
        $grupo = $prestamo->grupo;
        if ($grupo) {
            $grupo->estado_grupo = $prestamo->estado;
            $grupo->save();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
