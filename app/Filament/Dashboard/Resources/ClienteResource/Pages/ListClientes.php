<?php

namespace App\Filament\Dashboard\Resources\ClienteResource\Pages;

use App\Filament\Dashboard\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\BulkAction;

class ListClientes extends ListRecords
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkAction::make('delete')
                ->label('Inactivar seleccionados')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->action(function ($records) {
                    // En lugar de eliminar, cambiar el estado a inactivo para todos los registros seleccionados
                    $records->each(function ($record) {
                        $record->estado_cliente = 'Inactivo';
                        $record->save();
                    });
                })
                ->deselectRecordsAfterCompletion()
        ];
    }
}
