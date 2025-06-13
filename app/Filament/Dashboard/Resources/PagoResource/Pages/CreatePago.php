<?php

namespace App\Filament\Dashboard\Resources\PagoResource\Pages;

use App\Filament\Dashboard\Resources\PagoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePago extends CreateRecord
{
    protected static string $resource = PagoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['estado_pago'])) {
            $data['estado_pago'] = 'Pendiente';
        }


        if (empty($data['fecha_pago'])) {
            $data['fecha_pago'] = now();
        }
          $data['monto_mora_pagada'] = $data['monto_mora_pagada'] ?? 0;

        return $data;
    }


    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    public function mount(): void
    {
        parent::mount();
        $cuotaGrupalId = request()->get('cuota_grupal_id');
        if ($cuotaGrupalId) {
            $cuota = \App\Models\CuotasGrupales::with('mora', 'prestamo.grupo')->find($cuotaGrupalId);
            if ($cuota) {
                // Calcular el saldo pendiente actual
                $pagosAprobados = $cuota->pagos()->where('estado_pago', 'Aprobado')->sum('monto_pagado');
                $montoCuota = floatval($cuota->monto_cuota_grupal);
                $montoMora = $cuota->mora ? abs($cuota->mora->monto_mora_calculado) : 0;
                $saldoPendiente = max(($montoCuota + $montoMora) - $pagosAprobados, 0);

                $this->form->fill([
                    'cuota_grupal_id' => $cuota->id,
                    'grupo_id' => $cuota->prestamo->grupo->id ?? null,
                    'numero_cuota' => $cuota->numero_cuota,
                    'monto_cuota' => $cuota->monto_cuota_grupal,
                    'monto_mora_pagada' => $montoMora,
                    'saldo_pendiente_actual' => $saldoPendiente,
                    'estado_pago' => 'Pendiente',
                    'fecha_pago' => now(),
                ]);
            }
        } else {

            $this->form->fill([
                'estado_pago' => 'Pendiente',
                'fecha_pago' => now(),
            ]);
        }
    }

    protected function getFormSchema(): array
    {
        return parent::getFormSchema();
    }
}
