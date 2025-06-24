<?php

namespace App\Filament\Dashboard\Resources\PrestamoResource\Pages;

use App\Filament\Dashboard\Resources\PrestamoResource;
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

        $clientesGrupo = $data['clientes_grupo'] ?? [];

        foreach ($clientesGrupo as $cliente) {
            $monto = floatval($cliente['monto'] ?? 0);
            if ($monto < 0) {
                throw new \Exception("No se permiten montos negativos.");
            }
            if ($monto < 100) {
                throw new \Exception("El monto mínimo por integrante debe ser S/ 100.");
            }
        }

        // Sumar todos los seguros
        $ciclos = [
            1 => ['max' => 400, 'seguro' => 6],
            2 => ['max' => 600, 'seguro' => 7],
            3 => ['max' => 800, 'seguro' => 8],
            4 => ['max' => 1000, 'seguro' => 9],
        ];
        $totalSeguro = 0;
        foreach ($clientesGrupo as $cli) {
            $monto = floatval($cli['monto'] ?? 0);

            if ($monto <= 400) {
                $totalSeguro += 6;
            } elseif ($monto <= 600) {
                $totalSeguro += 7;
            } elseif ($monto <= 800) {
                $totalSeguro += 8;
            } else {
                $totalSeguro += 9;
            }
        }

        $data['monto_devolver'] = isset($data['monto_devolver'])
            ? floatval($data['monto_devolver']) + $totalSeguro
            : $totalSeguro;

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
        1 => ['max' => 400],
        2 => ['max' => 600],
        3 => ['max' => 800],
        4 => ['max' => 1000],
    ];

    foreach ($clientesGrupo as $cli) {
        $clienteId = (int)($cli['id'] ?? 0);
        $cliente = $grupo->clientes()->where('clientes.id', $clienteId)->first();
        if (!$cliente) continue;

        $ciclo = (int)($cliente->ciclo ?? 1);
        $ciclo = max(1, min(4, $ciclo));
        $maxPrestamo = $ciclos[$ciclo]['max'];
        $montoSolicitado = min(floatval($cli['monto']), $maxPrestamo);

        if ($montoSolicitado <= 0) continue;

        // Cálculo del seguro según el monto solicitado
        if ($montoSolicitado <= 400) {
            $seguro = 6;
        } elseif ($montoSolicitado <= 600) {
            $seguro = 7;
        } elseif ($montoSolicitado <= 800) {
            $seguro = 8;
        } else {
            $seguro = 9;
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
            'interes' => $tasaInteres,  // Guardaría el porcentaje (17)
            'estado' => 'Pendiente',
        ]);
    }

    // NO SE GENERAN CUOTAS GRUPALES AQUÍ
}

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
