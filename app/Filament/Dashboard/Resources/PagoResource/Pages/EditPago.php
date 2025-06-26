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

    /**
     * Determina si el formulario debe estar deshabilitado
     */
    protected function isFormDisabled(): bool
    {
        return $this->shouldDisableForm();
    }

    /**
     * Verifica si el formulario debe estar deshabilitado
     */

private function shouldDisableForm(): bool
{
    $user = Auth::user();

    if (strtolower($this->record->estado_pago) !== 'pendiente') {
        return true;
    }


    if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
        return true;
    }


    if ($user->hasRole('Asesor')) {
        $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
        $grupo = optional($this->record->cuotaGrupal?->prestamo?->grupo);

        if (!$asesor || !$grupo || $grupo->asesor_id !== $asesor->id) {
            return true;
        }

        return false;
    }

    return true;
}


    /**
     * Mount method - se ejecuta al cargar la página
     */
    public function mount(int | string $record): void
    {
        parent::mount($record);

        
        $user = Auth::user();
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']) &&
            strtolower($this->record->estado_pago) === 'pendiente') {


        }
    }

    /**
     * Mutate form data before save
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Auth::user();

        // Verificar que el pago esté pendiente
        if (strtolower($this->record->estado_pago) !== 'pendiente') {
            throw \Filament\Forms\Components\Component::make()->getValidationException([
                'estado_pago' => 'Solo se puede editar un pago en estado pendiente.',
            ]);
        }

        // Verificar que los super_admin y jefes no puedan editar
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            throw \Filament\Forms\Components\Component::make()->getValidationException([
                'general' => 'No puedes editar pagos. Solo puedes aprobar o rechazar.',
            ]);
        }

        // Para asesores, verificar que sea su grupo
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            $grupo = optional($this->record->cuotaGrupal?->prestamo?->grupo);

            if (!$asesor || !$grupo || $grupo->asesor_id !== $asesor->id) {
                throw \Filament\Forms\Components\Component::make()->getValidationException([
                    'general' => 'No tienes permisos para editar este pago.',
                ]);
            }
        }

        return $data;
    }

    /**
     * Obtener las acciones del formulario
     */
    protected function getFormActions(): array
    {
        $user = Auth::user();

        // Si el pago no está pendiente, no mostrar acciones de edición
        if (strtolower($this->record->estado_pago) !== 'pendiente') {
            return [];
        }

        // Si es super_admin, Jefe de operaciones o Jefe de créditos, no mostrar acciones de edición
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return [];
        }

        // Para asesores, verificar que sea su grupo
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            $grupo = optional($this->record->cuotaGrupal?->prestamo?->grupo);

            if (!$asesor || !$grupo || $grupo->asesor_id !== $asesor->id) {
                return [];
            }
        }

        return parent::getFormActions();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        $user = Auth::user();

        return [
            Actions\Action::make('aprobar')
                ->label('Aprobar')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->outlined()
                ->size('sm')
                ->visible(fn($record) =>
                    in_array(strtolower($record->estado_pago), ['pendiente']) &&
                    $user->hasAnyRole(['super_admin', 'Jefe de operaciones'])
                )
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
                ->visible(fn($record) =>
                    in_array(strtolower($record->estado_pago), ['pendiente']) &&
                    $user->hasAnyRole(['super_admin', 'Jefe de operaciones'])
                )
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
