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

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // Validar permisos antes de mostrar el formulario
        $user = \Illuminate\Support\Facades\Auth::user();
        
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            $esCreador = $asesor && $this->record->grupo && $this->record->grupo->asesor_id == $asesor->id;
            
            if (!$esCreador) {
                Notification::make()
                    ->title('Sin permisos')
                    ->body('No tienes permisos para editar este préstamo porque no eres el asesor que lo creó.')
                    ->danger()
                    ->send();
                    
                $this->redirect(static::getResource()::getUrl('index'));
                return;
            }
            
            if ($this->record->estado !== 'Pendiente') {
                Notification::make()
                    ->title('Préstamo no editable')
                    ->body('Solo puedes editar préstamos que estén en estado "Pendiente".')
                    ->warning()
                    ->send();
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldEstado = $this->record->estado;
        $user = \Illuminate\Support\Facades\Auth::user();

        // Validación de permisos para asesores
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            $esCreador = $asesor && $this->record->grupo && $this->record->grupo->asesor_id == $asesor->id;
            
            // Si no es el creador o el préstamo no está en Pendiente, no puede modificar
            if (!$esCreador || $this->record->estado !== 'Pendiente') {
                // Conservar todos los datos originales excepto los permitidos
                $data = [
                    'id' => $this->record->id,
                    'estado' => $this->record->estado, // No puede cambiar el estado
                ];
            }
        }
        
        // Validación para jefes - solo pueden cambiar el estado
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

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
