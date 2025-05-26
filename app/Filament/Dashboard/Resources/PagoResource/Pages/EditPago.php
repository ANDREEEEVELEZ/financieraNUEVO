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
            Actions\Action::make('aprobar')
                ->label('Aprobar')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->outlined()
                ->size('sm')
                ->visible(fn($record) => in_array(strtolower($record->estado_pago), ['pendiente']) && \Illuminate\Support\Facades\Auth::user()?->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
                ->action(function ($record) {
                    $record->aprobar();
                    \Filament\Notifications\Notification::make()
                        ->title('Pago aprobado')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('rechazar')
                ->label('Rechazar')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->outlined()
                ->size('sm')
                ->visible(fn($record) => in_array(strtolower($record->estado_pago), ['pendiente']) && \Illuminate\Support\Facades\Auth::user()?->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
                ->action(function ($record) {
                    $record->rechazar();
                    \Filament\Notifications\Notification::make()
                        ->title('Pago rechazado')
                        ->danger()
                        ->send();
                }),
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
    
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
