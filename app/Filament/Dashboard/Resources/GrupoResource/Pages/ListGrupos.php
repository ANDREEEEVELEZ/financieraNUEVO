<?php

namespace App\Filament\Dashboard\Resources\GrupoResource\Pages;

use App\Filament\Dashboard\Resources\GrupoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGrupos extends ListRecords
{
    protected static string $resource = GrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->icon('heroicon-o-plus-circle'),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Actions\Action::make('imprimir_contratos')
                ->label('Imprimir Contratos')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn($record) => route('contratos.grupo.imprimir', $record->id))
                ->openUrlInNewTab(),
        ];
    }
}
