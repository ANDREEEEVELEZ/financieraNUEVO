<?php

namespace App\Filament\Dashboard\Resources\RetanqueoResource\Pages;

use App\Filament\Dashboard\Resources\RetanqueoResource;
use App\Services\RetanqueoService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class EditRetanqueo extends EditRecord
{
    protected static string $resource = RetanqueoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->icon('heroicon-m-eye'),
            Actions\DeleteAction::make()
                ->visible(fn () => request()->user()->hasRole('super_admin') && $this->record->esSolicitudPendiente()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Cargar los participantes existentes
        $retanqueo = $this->record;
        $participantes = [];

        foreach ($retanqueo->retanqueosIndividuales as $individual) {
            $cliente = $individual->cliente;
            $participantes[] = [
                'cliente_id' => $cliente->id,
                'nombre_completo' => $cliente->persona->nombre . ' ' . $cliente->persona->apellidos,
                'ciclo' => \App\Helpers\CicloHelper::normalize($cliente->ciclo ?? 'I'),
                'monto_maximo' => \App\Helpers\CicloHelper::getMontoMaximo(\App\Helpers\CicloHelper::normalize($cliente->ciclo ?? 'I')),
                'participacion_tipo' => $individual->participacion_tipo,
                'monto_solicitado' => $individual->monto_solicitado,
            ];
        }

        $data['participantes'] = $participantes;
        $data['cantidad_cuotas_nuevo'] = $retanqueo->cantidad_cuotas_nuevo;

        // Cargar información del estado del préstamo
        try {
            $retanqueoService = new RetanqueoService();
            $estadoPrestamo = $retanqueoService->calcularEstadoPrestamo($retanqueo->prestamo_id);
            $data['estado_prestamo_info'] = [
                'monto_prestado' => $estadoPrestamo['monto_prestado_original'],
                'saldo_pendiente' => $estadoPrestamo['saldo_pendiente_total'],
                'cuotas_pagadas' => $estadoPrestamo['cuotas_pagadas'],
                'cuotas_total' => $estadoPrestamo['cuotas_total'],
                'porcentaje_pagado' => $estadoPrestamo['porcentaje_pagado']
            ];
        } catch (\Exception $e) {
            Log::error('Error al cargar estado del préstamo para edición', [
                'retanqueo_id' => $retanqueo->id,
                'prestamo_id' => $retanqueo->prestamo_id,
                'error' => $e->getMessage()
            ]);
            $data['estado_prestamo_info'] = null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Verificar que solo se pueden editar solicitudes pendientes
        if (!$this->record->esSolicitudPendiente()) {
            throw new \Exception('Solo se pueden editar solicitudes pendientes');
        }

        // Limpiar datos innecesarios
        unset($data['estado_prestamo_info']);
        
        return $data;
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            $retanqueoService = new RetanqueoService();
            
            // Extraer participantes del array de datos
            $participantes = $data['participantes'] ?? [];
            
            // Eliminar retanqueos individuales existentes
            $record->retanqueosIndividuales()->delete();
            
            // Crear nuevos retanqueos individuales
            $totalRetanqueo = 0;
            $totalCobertura = 0;

            foreach ($participantes as $participante) {
                $cliente = \App\Models\Cliente::find($participante['cliente_id']);
                if (!$cliente) {
                    continue;
                }

                // Validar monto según ciclo del cliente
                $ciclo = \App\Helpers\CicloHelper::normalize($cliente->ciclo ?? 'I');
                $montoMaximo = \App\Helpers\CicloHelper::getMontoMaximo($ciclo);
                $montoSolicitado = min($participante['monto_solicitado'] ?? 0, $montoMaximo);

                if ($montoSolicitado < 0) {
                    $montoSolicitado = 0;
                }

                // Calcular aporte de cobertura si retanquea
                $aporteCobertura = 0;
                if ($participante['participacion_tipo'] === 'retanquea') {
                    $estadoPrestamo = $retanqueoService->calcularEstadoPrestamo($record->prestamo_id);
                    $integrantesQueRetanquean = collect($participantes)->where('participacion_tipo', 'retanquea')->count();
                    
                    if ($integrantesQueRetanquean > 0) {
                        $aporteCobertura = round($estadoPrestamo['saldo_pendiente_total'] / $integrantesQueRetanquean, 2);
                    }
                }

                \App\Models\RetanqueoIndividual::create([
                    'retanqueo_id' => $record->id,
                    'cliente_id' => $participante['cliente_id'],
                    'participacion_tipo' => $participante['participacion_tipo'],
                    'aporte_cobertura' => $aporteCobertura,
                    'monto_solicitado' => $montoSolicitado,
                    'monto_desembolsar' => $montoSolicitado - $aporteCobertura,
                    'monto_cuota' => null, // Se calculará al aprobar
                    'aceptacion_cliente' => 0,
                    'estado_retanqueo_individual' => 'propuesto'
                ]);

                if (in_array($participante['participacion_tipo'], ['retanquea', 'nueva'])) {
                    $totalRetanqueo += $montoSolicitado;
                }

                if ($participante['participacion_tipo'] === 'retanquea') {
                    $totalCobertura += $aporteCobertura;
                }
            }

            // Actualizar totales del retanqueo
            $estadoPrestamo = $retanqueoService->calcularEstadoPrestamo($record->prestamo_id);
            $saldoRestante = max(0, $estadoPrestamo['saldo_pendiente_total'] - $totalCobertura);

            $record->update([
                'cantidad_cuotas_nuevo' => $data['cantidad_cuotas_nuevo'] ?? 20,
                'monto_retanqueo' => $totalRetanqueo,
                'monto_usado_para_cubrir_antiguo' => $totalCobertura,
                'monto_desembolsar' => $totalRetanqueo - $totalCobertura,
                'saldo_restante_prestamo_antiguo' => $saldoRestante
            ]);

            Log::info('Solicitud de retanqueo actualizada', [
                'retanqueo_id' => $record->id,
                'user_id' => request()->user()?->id
            ]);

            return $record->fresh();

        } catch (\Exception $e) {
            Log::error('Error al actualizar solicitud de retanqueo', [
                'retanqueo_id' => $record->id,
                'error' => $e->getMessage(),
                'user_id' => request()->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    protected function afterSave(): void
    {
        Notification::make()
            ->title('Solicitud Actualizada')
            ->body('La solicitud de retanqueo ha sido actualizada exitosamente.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Actualizar Solicitud'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getSaveFormAction(): Actions\Action
    {
        return Actions\Action::make('save')
            ->label('Actualizar Solicitud')
            ->submit('save')
            ->keyBindings(['mod+s'])
            ->color('primary')
            ->icon('heroicon-m-check')
            ->requiresConfirmation()
            ->modalHeading('Confirmar Actualización')
            ->modalDescription('¿Está seguro de que desea actualizar esta solicitud de retanqueo?')
            ->modalSubmitActionLabel('Sí, actualizar');
    }

    protected function authorizeAccess(): void
    {
        $user = request()->user();
        
        if (!$user) {
            $this->redirect('/');
            return;
        }

        // Verificar que el record se puede editar
        if (!static::getResource()::canEdit($this->record)) {
            abort(403, 'No tiene permisos para editar este retanqueo');
        }

        // Verificar que solo se pueden editar solicitudes pendientes
        if (!$this->record->esSolicitudPendiente()) {
            abort(403, 'Solo se pueden editar solicitudes pendientes');
        }
    }
}
