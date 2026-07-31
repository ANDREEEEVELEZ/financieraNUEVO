<?php

namespace App\Filament\Dashboard\Resources\RetanqueoResource\Pages;

use App\Filament\Dashboard\Resources\RetanqueoResource;
use App\Contracts\RetanqueoQueryInterface;
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
                ->visible(fn () => (bool) auth()->user()?->can('delete', $this->record)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Cargar los datos del retanqueo
        $retanqueo = $this->record;
        
        // CRÍTICO: Asegurar que prestamo_id esté en el formulario
        $data['prestamo_id'] = $retanqueo->prestamo_id;
        
        // Cargar los participantes existentes con todos los datos necesarios
        $participantes = [];

        foreach ($retanqueo->retanqueosIndividuales as $individual) {
            $cliente = $individual->cliente;
            if ($cliente && $cliente->persona) {
                $participantes[] = [
                    'cliente_id' => $cliente->id,
                    'nombre_completo' => $cliente->persona->nombre . ' ' . $cliente->persona->apellidos,
                    'ciclo' => \App\Helpers\CicloHelper::normalize($cliente->ciclo ?? 'I'),
                    'monto_maximo' => \App\Helpers\CicloHelper::getMontoMaximo(\App\Helpers\CicloHelper::normalize($cliente->ciclo ?? 'I')),
                    'participacion_tipo' => $individual->participacion_tipo,
                    'monto_solicitado' => (float)$individual->monto_solicitado,
                ];
            }
        }

        $data['participantes'] = $participantes;
        $data['cantidad_cuotas_nuevo'] = $retanqueo->cantidad_cuotas_nuevo ?? 4;

        // Cargar información del estado del préstamo
        try {
            $estadoPrestamo = app(RetanqueoQueryInterface::class)->calcularEstadoPrestamo($retanqueo->prestamo_id);
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

        // Cargar datos de cuenta de desembolso si existe un préstamo nuevo asociado
        if ($retanqueo->prestamo_nuevo_id && $retanqueo->prestamoNuevo) {
            $prestamoNuevo = $retanqueo->prestamoNuevo;
            $data['titular_cuenta_desembolso'] = $prestamoNuevo->titular_cuenta_desembolso;
            $data['numero_cuenta_desembolso'] = $prestamoNuevo->numero_cuenta_desembolso;
        }

        Log::info('Datos cargados para editar retanqueo', [
            'retanqueo_id' => $retanqueo->id,
            'prestamo_id' => $data['prestamo_id'],
            'participantes_count' => count($participantes),
            'tiene_cuenta_desembolso' => !empty($data['titular_cuenta_desembolso'])
        ]);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Verificar que solo se pueden editar solicitudes pendientes
        if (!$this->record->esSolicitudPendiente()) {
            throw new \Exception('Solo se pueden editar solicitudes pendientes');
        }

        // Limpiar datos innecesarios para el procesamiento
        unset($data['estado_prestamo_info']);
        
        // IMPORTANTE: NO remover los datos de cuenta si están presentes y válidos
        // Estos son necesarios para actualizar el préstamo pendiente asociado
        Log::info('Datos de cuenta en edición', [
            'retanqueo_id' => $this->record->id,
            'tiene_titular' => !empty($data['titular_cuenta_desembolso']),
            'tiene_numero' => !empty($data['numero_cuenta_desembolso'])
        ]);
        
        return $data;
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            $queryService = app(RetanqueoQueryInterface::class);

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
                    $estadoPrestamo = $queryService->calcularEstadoPrestamo($record->prestamo_id);
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
            $estadoPrestamo = $queryService->calcularEstadoPrestamo($record->prestamo_id);
            $saldoRestante = max(0, $estadoPrestamo['saldo_pendiente_total'] - $totalCobertura);

            $record->update([
                'cantidad_cuotas_nuevo' => $data['cantidad_cuotas_nuevo'] ?? 4, // CORREGIDO: Por defecto 4 cuotas
                'monto_retanqueo' => $totalRetanqueo,
                'monto_usado_para_cubrir_antiguo' => $totalCobertura,
                'monto_desembolsar' => $totalRetanqueo - $totalCobertura,
                'saldo_restante_prestamo_antiguo' => $saldoRestante
            ]);

            // NUEVO: Actualizar préstamo pendiente asociado si existe y hay datos de cuenta
            if ($record->prestamo_nuevo_id && $record->prestamoNuevo) {
                $prestamoNuevo = $record->prestamoNuevo;
                
                // Actualizar datos de cuenta si se proporcionaron
                $datosCuentaActualizados = [];
                if (!empty($data['titular_cuenta_desembolso'])) {
                    $datosCuentaActualizados['titular_cuenta_desembolso'] = $data['titular_cuenta_desembolso'];
                }
                if (!empty($data['numero_cuenta_desembolso'])) {
                    $datosCuentaActualizados['numero_cuenta_desembolso'] = $data['numero_cuenta_desembolso'];
                }
                
                // Actualizar montos del préstamo pendiente
                $datosCuentaActualizados['monto_prestado_total'] = $totalRetanqueo;
                
                $prestamoNuevo->update($datosCuentaActualizados);
                
                // Actualizar préstamos individuales del préstamo pendiente
                $prestamoNuevo->prestamoIndividual()->delete();
                
                $montoTotalDevolver = 0;
                foreach ($participantes as $participante) {
                    if (in_array($participante['participacion_tipo'], ['retanquea', 'nueva'])) {
                        $cliente = \App\Models\Cliente::find($participante['cliente_id']);
                        $montoSolicitado = $participante['monto_solicitado'];
                        $tasaInteres = $prestamoNuevo->tasa_interes ?? 17;
                        $numCuotas = $prestamoNuevo->cantidad_cuotas;

                        // Calcular seguro según monto
                        $montoInt = (int) $montoSolicitado;
                        if ($montoInt === 400) {
                            $seguro = 7;  // Ciclo I
                        } elseif ($montoInt === 500 || $montoInt === 600) {
                            $seguro = 8;  // Ciclo II
                        } elseif ($montoInt === 700 || $montoInt === 800) {
                            $seguro = 9;  // Ciclo III
                        } elseif ($montoInt === 900 || $montoInt === 1000) {
                            $seguro = 10; // Ciclo IV
                        } else {
                            $seguro = 7;
                        }
                        
                        // Calcular interés y total a devolver
                        $interes = $montoSolicitado * ($tasaInteres / 100);
                        $montoDevolver = $montoSolicitado + $interes + $seguro;
                        $cuotaIndividual = $montoDevolver / $numCuotas;

                        \App\Models\PrestamoIndividual::create([
                            'prestamo_id' => $prestamoNuevo->id,
                            'cliente_id' => $cliente->id,
                            'monto_prestado_individual' => $montoSolicitado,
                            'monto_cuota_prestamo_individual' => round($cuotaIndividual, 2),
                            'monto_devolver_individual' => round($montoDevolver, 2),
                            'seguro' => $seguro,
                            'interes' => round($interes, 2),
                            'estado' => 'Pendiente'
                        ]);

                        $montoTotalDevolver += $montoDevolver;
                    }
                }
                
                // Actualizar monto a devolver del préstamo
                $prestamoNuevo->update([
                    'monto_devolver' => round($montoTotalDevolver, 2)
                ]);
                
                Log::info('Préstamo pendiente actualizado en edición de retanqueo', [
                    'retanqueo_id' => $record->id,
                    'prestamo_nuevo_id' => $prestamoNuevo->id,
                    'nuevos_montos' => $datosCuentaActualizados
                ]);
            }

            Log::info('Solicitud de retanqueo actualizada', [
                'retanqueo_id' => $record->id,
                'user_id' => request()->user()?->id,
                'total_participantes' => count($participantes),
                'total_retanqueo' => $totalRetanqueo
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
