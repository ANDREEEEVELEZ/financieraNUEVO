<?php

namespace App\Filament\Dashboard\Resources\PagoResource\Pages;

use App\Filament\Dashboard\Resources\PagoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;

class EditPago extends EditRecord
{
    protected static string $resource = PagoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // No eliminar 'estado_pago', permitir que se guarde lo que venga del formulario
        return $data;
    }

    protected function getFormSchema(): array
    {
        $schema = parent::getFormSchema();
        // Deshabilitar el campo estado_pago si el usuario no tiene el rol adecuado
        foreach ($schema as &$component) {
            if (method_exists($component, 'getName') && $component->getName() === 'estado_pago') {
                $component = $component->disabled();
            }
        }
        return $schema;
    }
}
