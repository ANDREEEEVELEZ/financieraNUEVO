<?php

namespace App\Filament\Dashboard\Resources\PrestamoResource\Pages;

use App\Filament\Dashboard\Resources\PrestamoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\PrestamoIndividual;
use App\Models\Grupo;

class EditPrestamo extends EditRecord
{
    protected static string $resource = PrestamoResource::class;

    /** @var string|null */
    protected $oldEstado = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldEstado = $this->record->estado;

        if (isset($data['nuevo_rol']) && !empty($data['nuevo_rol'])) {
            $user = \Illuminate\Support\Facades\Auth::user();
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
                    'interes' => $tasaInteres,  // Guardaría el porcentaje (17)
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
