<?php

namespace App\Filament\Dashboard\Resources\PrestamoResource\Pages;

use App\Filament\Dashboard\Resources\PrestamoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\PrestamoIndividual;
use App\Models\Grupo;
use Filament\Notifications\Notification;

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
        $user = \Illuminate\Support\Facades\Auth::user();
        
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
        return [
            // Si el préstamo no está en estado Pendiente, no mostrar acciones de edición
            // Actions\DeleteAction::make()->icon('heroicon-o-trash'), BOTON DE ELIMINAR DESHABILITADO POR REQUERIMIENTO
        ];
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
        $user = \Illuminate\Support\Facades\Auth::user();

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
        
        // Validación para jefes - solo pueden cambiar el estado (y solo si está en Pendiente)
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            // Conservar todos los campos originales excepto el estado
            $allowedData = [
                'estado' => $data['estado'] ?? $this->record->estado,
            ];
            $data = array_merge($this->record->toArray(), $allowedData);
        }

        if (isset($data['nuevo_rol']) && !empty($data['nuevo_rol'])) {
            if ($user->hasAnyRole(['Jefe de operaciones', 'Jefe de creditos'])) {
                $user->syncRoles([$data['nuevo_rol']]);
            }
        }

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
            $numCuotas = $prestamo->cantidad_cuotas;

            $ciclos = [
                1 => ['max' => 400, 'seguro' => 6],
                2 => ['max' => 600, 'seguro' => 7],
                3 => ['max' => 800, 'seguro' => 8],
                4 => ['max' => 1000, 'seguro' => 9],
            ];

            foreach ($clientesGrupo as $cli) {
                $clienteId = (int)($cli['id'] ?? 0);
                $cliente = $grupo->clientes()->where('clientes.id', $clienteId)->first();
                if (!$cliente) continue;

                $ciclo = (int)($cliente->ciclo ?? 1);
                $ciclo = $ciclo > 4 ? 4 : ($ciclo < 1 ? 1 : $ciclo);
                $maxPrestamo = $ciclos[$ciclo]['max'];
                $seguro = $ciclos[$ciclo]['seguro'];

                $montoSolicitado = min(floatval($cli['monto']), $maxPrestamo);
                if ($montoSolicitado <= 0) continue;

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
