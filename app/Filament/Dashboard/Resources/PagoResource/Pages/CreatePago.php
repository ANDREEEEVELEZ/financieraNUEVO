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
        // Solo establecer 'Pendiente' si no viene del formulario
      
        if (empty($data['estado_pago'])) {
            $data['estado_pago'] = 'Pendiente';
        }
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
                $this->form->fill([
                    'cuota_grupal_id' => $cuota->id,
                    'grupo_id' => $cuota->prestamo->grupo->id ?? null,
                    'numero_cuota' => $cuota->numero_cuota,
                    'monto_cuota' => $cuota->monto_cuota_grupal,
                    // No prellenar monto_pagado ni tipo_pago ni monto_mora_aplicada
                    'estado_pago' => 'Pendiente',
                ]);
            }
        } else {
            // Si no viene de moras, asegurar que el estado sea pendiente por defecto
            $this->form->fill([
                'estado_pago' => 'Pendiente',
            ]);
        }
    }


    protected function getFormSchema(): array
    {
        return parent::getFormSchema();
    }
}

