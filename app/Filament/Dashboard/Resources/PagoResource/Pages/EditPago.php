<?php

namespace App\Filament\Dashboard\Resources\PagoResource\Pages;

use App\Filament\Dashboard\Resources\PagoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class EditPago extends EditRecord
{
    protected static string $resource = PagoResource::class;

    /*
    protected function isFormDisabled(): bool
    {
        return strtolower($this->record->estado_pago) !== 'pendiente';
    }
        */
    protected function mutateFormDataBeforeSave(array $data): array
{
    if (strtolower($this->record->estado_pago) !== 'pendiente') {
        throw \Filament\Forms\Components\Component::make()->getValidationException([
            'estado_pago' => 'Solo se puede editar un pago en estado pendiente.',
        ]);
    }

    return $data;
}


protected function getFormActions(): array
{
    if (strtolower($this->record->estado_pago) !== 'pendiente') {
        return [];
    }

    return parent::getFormActions();
}
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }



    protected function getHeaderActions(): array
    {

        return [
            Actions\Action::make('aprobar')
                ->label('Aprobar')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->outlined()
                ->size('sm')
                ->visible(fn($record) => in_array(strtolower($record->estado_pago), ['pendiente']) &&
                    Auth::user()?->hasAnyRole(['super_admin', 'Jefe de operaciones']))
                ->action(function ($record) {
                    $record->aprobar();
                    Notification::make()
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
                ->visible(fn($record) => in_array(strtolower($record->estado_pago), ['pendiente']) &&
                    Auth::user()?->hasAnyRole(['super_admin', 'Jefe de operaciones', ]))
                ->action(function ($record) {
                    $record->rechazar();
                    Notification::make()
                        ->title('Pago rechazado')
                        ->danger()
                        ->send();
                }),
        ];
    }
}
