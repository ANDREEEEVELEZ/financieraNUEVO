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
                ->visible(fn($record) => in_array(strtolower($record->estado_pago), ['pendiente']) &&
                    Auth::user()?->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de Creditos']))
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
                    Auth::user()?->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de Creditos']))
                ->action(function ($record) {
                    $record->rechazar();
                    Notification::make()
                        ->title('Pago rechazado')
                        ->danger()
                        ->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function getFormSchema(): array
    {
        $canEditEstado = Auth::user()?->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de Creditos']);

        return [
            Forms\Components\Select::make('estado_pago')
                ->label('Estado del pago')
                ->options([
                    'Pendiente' => 'Pendiente',
                    'Aprobado' => 'Aprobado',
                    'Rechazado' => 'Rechazado',
                ])
                ->disabled(! $canEditEstado),
            
            Forms\Components\TextInput::make('monto')
                ->label('Monto')
                ->required()
                ->numeric(),
            
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
