<?php

namespace App\Filament\Dashboard\Resources\ClienteResource\Pages;

use App\Filament\Dashboard\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\Action;

class ListClientes extends ListRecords
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\ClienteStatsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('activar')
                ->label('Activar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => $record->estado_cliente === 'Inactivo')
                ->action(function ($record) {
                    $record->estado_cliente = 'Activo';
                    $record->save();
                })
                ->requiresConfirmation()
                ->modalHeading('¿Activar cliente?')
                ->modalDescription('¿Estás seguro de que quieres activar este cliente?')
                ->modalSubmitActionLabel('Sí, activar')
                ->modalCancelActionLabel('No, cancelar'),
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
                ->deselectRecordsAfterCompletion(),
                
            BulkAction::make('activate')
                ->label('Activar seleccionados')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->action(function ($records) {
                    $records->each(function ($record) {
                        $record->estado_cliente = 'Activo';
                        $record->save();
                    });
                })
                ->deselectRecordsAfterCompletion()
        ];
    }
}
