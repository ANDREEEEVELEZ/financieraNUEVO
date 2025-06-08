<?php

namespace App\Filament\Dashboard\Resources\PrestamoResource\Pages;

use App\Filament\Dashboard\Resources\PrestamoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\PrestamoIndividual;
use App\Models\Grupo;

class CreatePrestamo extends CreateRecord
{
    protected static string $resource = PrestamoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['estado'] = 'Pendiente';
        $data['frecuencia'] = 'semanal';
        // Ajuste: sumar todos los seguros al monto a devolver
        $clientesGrupo = $data['clientes_grupo'] ?? [];
        $ciclos = [
            1 => ['max' => 400, 'seguro' => 6],
            2 => ['max' => 600, 'seguro' => 7],
            3 => ['max' => 800, 'seguro' => 8],
            4 => ['max' => 1000, 'seguro' => 9],
        ];
        $totalSeguro = 0;
        foreach ($clientesGrupo as $cli) {
            $ciclo = (int)($cli['ciclo'] ?? 1);
            $ciclo = $ciclo > 4 ? 4 : ($ciclo < 1 ? 1 : $ciclo);
            $totalSeguro += $ciclos[$ciclo]['seguro'];
        }
        $data['monto_devolver'] = isset($data['monto_devolver']) ? floatval($data['monto_devolver']) + $totalSeguro : $totalSeguro;
        return $data;
    }

    protected function afterCreate(): void
    {
        $prestamo = $this->record;
        $grupo = $prestamo->grupo;
        $clientesGrupo = $this->data['clientes_grupo'] ?? [];
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
                'interes' => $interes, // Mostrar el interés calculado
                'estado' => 'Pendiente',
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
