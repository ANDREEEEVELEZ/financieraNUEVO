<?php

namespace App\Filament\Dashboard\Resources\PrestamoResource\Pages;

use App\Filament\Dashboard\Resources\PrestamoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\PrestamoIndividual;
use App\Models\Grupo;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class EditPrestamo extends EditRecord
{
    protected static string $resource = PrestamoResource::class;

    /** @var string|null */
    protected $oldEstado = null;

    public function getTitle(): string
    {
        $titulo = parent::getTitle();
        
        // Si el préstamo no está en estado Pendiente, cambiar el título
        if ($this->record->estado !== 'Pendiente') {
            return $titulo . ' (Solo Lectura - Estado: ' . $this->record->estado . ')';
        }
        
        return $titulo;
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // Validar permisos antes de mostrar el formulario
        $user = Auth::user();
        
        // Si el préstamo NO está en estado Pendiente, mostrar notificación
        if ($this->record->estado !== 'Pendiente') {
            Notification::make()
                ->title('Préstamo solo de lectura')
                ->body('Este préstamo está en estado "' . $this->record->estado . '" y solo se puede visualizar. No se pueden realizar modificaciones.')
                ->warning()
                ->persistent()
                ->send();
        }
        
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            $esCreador = $asesor && $this->record->grupo && $this->record->grupo->asesor_id == $asesor->id;
            
            if (!$esCreador) {
                Notification::make()
                    ->title('Sin permisos')
                    ->body('No tienes permisos para ver este préstamo porque no eres el asesor que lo creó.')
                    ->danger()
                    ->send();
                    
                $this->redirect(static::getResource()::getUrl('index'));
                return;
            }
        }
        
        // Forzar la recarga de los datos para asegurar que los montos totales se muestren correctamente
        $this->sincronizarMontosTotal();
    }
    
    protected function sincronizarMontosTotal(): void
    {
        // Usar el método del modelo para sincronizar montos
        $this->record = $this->record->sincronizarMontosTotal();
    }

    protected function getHeaderActions(): array
    {
        $user = Auth::user();
        $actions = [];

        // Solo mostrar botones de aprobar/rechazar para roles mayores y préstamos pendientes
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']) && 
            $this->record->estado === 'Pendiente') {
            
            // Botón Aprobar
            $actions[] = Actions\Action::make('aprobar')
                ->label('Aprobar')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar Préstamo')
                ->modalDescription('¿Está seguro de que desea aprobar este préstamo?')
                ->modalSubmitActionLabel('Sí, Aprobar')
                ->action(function () {
                    $this->record->aprobar();
                    
                    Notification::make()
                        ->title('Préstamo Aprobado')
                        ->body('El préstamo ha sido aprobado exitosamente.')
                        ->success()
                        ->send();
                    
                    // Recargar la página para reflejar los cambios
                    return redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
                });

            // Botón Rechazar
            $actions[] = Actions\Action::make('rechazar')
                ->label('Rechazar')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Rechazar Préstamo')
                ->modalDescription('¿Está seguro de que desea rechazar este préstamo?')
                ->modalSubmitActionLabel('Sí, Rechazar')
                ->action(function () {
                    $this->record->rechazar();
                    
                    Notification::make()
                        ->title('Préstamo Rechazado')
                        ->body('El préstamo ha sido rechazado.')
                        ->danger()
                        ->send();
                    
                    // Recargar la página para reflejar los cambios
                    return redirect(static::getResource()::getUrl('edit', ['record' => $this->record]));
                });
        }

        return $actions;
    }

    protected function getFormActions(): array
    {
        // Si el préstamo no está en estado Pendiente, no mostrar el botón de guardar
        if ($this->record->estado !== 'Pendiente') {
            return [
                $this->getCancelFormAction(),
            ];
        }

        return parent::getFormActions();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldEstado = $this->record->estado;
        $user = Auth::user();

        // Si el préstamo NO está en estado Pendiente, no permitir ningún cambio
        if ($this->record->estado !== 'Pendiente') {
            // Retornar todos los datos originales sin modificaciones
            return $this->record->toArray();
        }

        // Validación de permisos para asesores
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            $esCreador = $asesor && $this->record->grupo && $this->record->grupo->asesor_id == $asesor->id;
            
            // Si no es el creador o el préstamo no está en Pendiente, no puede modificar
            if (!$esCreador || $this->record->estado !== 'Pendiente') {
                // Conservar todos los datos originales
                return $this->record->toArray();
            }
        }
        
        // Validación para jefes - ya no pueden cambiar el estado desde el formulario
        // El estado se cambia solo con los botones de aprobar/rechazar
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            // Conservar todos los campos originales, el estado no se puede cambiar desde el formulario
            $originalData = $this->record->toArray();
            // Permitir solo ciertos campos editables
            $allowedFields = ['monto_prestado_total', 'monto_devolver', 'fecha_prestamo', 'titular_cuenta_desembolso', 'numero_cuenta_desembolso'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $originalData[$field] = $data[$field];
                }
            }
            $data = $originalData;
        }

        if (isset($data['nuevo_rol']) && !empty($data['nuevo_rol'])) {
            if ($user->hasAnyRole(['Jefe de operaciones', 'Jefe de creditos'])) {
                $user->syncRoles([$data['nuevo_rol']]);
            }
        }

        // Forzar valores fijos para todos los préstamos
        $data['cantidad_cuotas'] = 4;
        $data['frecuencia'] = 'semanal';

        return parent::mutateFormDataBeforeSave($data);
    }

    protected function onSaved(): void
    {
        $prestamo = $this->record->fresh();
        $grupo = $prestamo->grupo;

        // Si el estado cambió a Aprobado y antes era Pendiente o Rechazado, generar préstamos individuales
        if (in_array($this->oldEstado, ['Pendiente', 'Rechazado']) && $prestamo->estado === 'Aprobado') {
            $clientesGrupo = json_decode($prestamo->getRawOriginal('clientes_grupo'), true) ?? [];
            $tasaInteres = $prestamo->tasa_interes ?? 17;
            $numCuotas = 4; // Fijo en 4 cuotas para todos los préstamos

            foreach ($clientesGrupo as $cli) {
                $clienteId = (int)($cli['id'] ?? 0);
                $cliente = $grupo->clientes()->where('clientes.id', $clienteId)->first();
                if (!$cliente) continue;

                // Usar el monto exacto que se ingresó en el formulario
                // El formulario ya valida que el monto no exceda el límite del ciclo
                $montoSolicitado = floatval($cli['monto']);
                if ($montoSolicitado <= 0) continue;

                // Calcular seguro según tabla oficial exacta
                $montoSolicitadoInt = (int) $montoSolicitado;
                
                if ($montoSolicitadoInt === 400) {
                    $seguro = 7;  // Ciclo I
                } elseif ($montoSolicitadoInt === 500 || $montoSolicitadoInt === 600) {
                    $seguro = 8;  // Ciclo II
                } elseif ($montoSolicitadoInt === 700 || $montoSolicitadoInt === 800) {
                    $seguro = 9;  // Ciclo III
                } elseif ($montoSolicitadoInt === 900 || $montoSolicitadoInt === 1000) {
                    $seguro = 10; // Ciclo IV
                } else {
                    // Fallback para montos no estándar
                    $seguro = 7;
                }

                $interes = $montoSolicitado * ($tasaInteres / 100);
                $montoDevolver = $montoSolicitado + $interes + $seguro;
                $cuotaSemanal = $montoDevolver / $numCuotas;

                PrestamoIndividual::create([
                    'prestamo_id' => $prestamo->id,
                    'cliente_id' => $cliente->id,
                    'monto_prestado_individual' => $montoSolicitado,
                    'monto_cuota_prestamo_individual' => round($cuotaSemanal, 2),
                    'monto_devolver_individual' => round($montoDevolver, 2),
                    'seguro' => $seguro,
                    'interes' => $interes,  // Guardamos el monto del interés calculado, no el porcentaje
                    'estado' => 'Pendiente',
                ]);
            }
        }

        // Sincronizar el estado con el grupo
        if ($grupo) {
            $grupo->estado_grupo = $prestamo->estado;
            $grupo->save();
        }

        // Verificar si ya debe finalizarse automáticamente (opcional)
        $prestamo->actualizarEstadoAutomaticamente();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Asegurar que el monto_prestado_total se cargue correctamente
        if (!empty($data['id'])) {
            $prestamo = \App\Models\Prestamo::with('prestamoIndividual')->find($data['id']);
            if ($prestamo) {
                // Recalcular el monto total basado en los préstamos individuales
                $montoTotal = $prestamo->prestamoIndividual->sum('monto_prestado_individual');
                $montoDevolver = $prestamo->prestamoIndividual->sum('monto_devolver_individual');
                
                $data['monto_prestado_total'] = $montoTotal;
                $data['monto_devolver'] = $montoDevolver;
            }
        }
        
        return $data;
    }

    protected function afterSaved(): void
    {
        // Los totales se actualizan automáticamente a través del PrestamoIndividualObserver
        Notification::make()
            ->title('Préstamo actualizado')
            ->body('Los cambios han sido guardados correctamente.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
