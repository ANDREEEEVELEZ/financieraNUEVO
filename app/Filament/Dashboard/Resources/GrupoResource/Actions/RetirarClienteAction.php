<?php

namespace App\Filament\Dashboard\Resources\GrupoResource\Actions;

use App\Models\Grupo;
use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class RetirarClienteAction
{
    public static function retirar(Grupo $grupo, Cliente $cliente): bool
    {
        // Validar que el cliente no tenga deuda en el grupo
        $prestamo = $grupo->prestamos()->orderByDesc('id')->first();
        if ($prestamo) {
            $cuotas = $prestamo->cuotasGrupales()->whereHas('pagos', function($q) use ($cliente) {
                $q->where('cliente_id', $cliente->id);
            })->get();
            foreach ($cuotas as $cuota) {
                $pagosPendientes = $cuota->pagos()->where('cliente_id', $cliente->id)
                    ->whereIn('estado_pago', ['pendiente', 'parcial'])
                    ->exists();
                $moraPendiente = $cuota->mora && in_array($cuota->mora->estado_mora, ['pendiente', 'parcial']);
                if ($pagosPendientes || $moraPendiente) {
                    Notification::make()
                        ->danger()
                        ->title('No se puede retirar')
                        ->body('El cliente tiene deuda pendiente en este grupo.')
                        ->send();
                    return false;
                }
            }
        }
        // Actualizar el estado en la tabla pivote grupo_cliente
        $grupo->clientes()->updateExistingPivot($cliente->id, [
            'estado_grupo_cliente' => 'Retirado',
            'fecha_salida' => now()->toDateString(),
        ]);
        Notification::make()
            ->success()
            ->title('Cliente retirado')
            ->body('El cliente fue retirado del grupo y queda en el historial.')
            ->send();
        return true;
    }
}
