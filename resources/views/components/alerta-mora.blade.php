{{-- resources/views/components/alerta-mora.blade.php --}}
@if(isset($get) && $cuota = \App\Models\CuotasGrupales::with('mora')->find($get('cuota_grupal_id')))
    @if($cuota->mora && in_array($cuota->mora->estado_mora, ['pendiente', 'parcialmente_pagada']))
        {{-- Mostrar alerta si la cuota tiene mora pendiente o parcialmente pagada --}}
        <div class="rounded bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-3 mb-2">
            <strong>¡Atención!</strong> Esta cuota tiene una <b>mora {{ $cuota->mora->estado_mora === 'pendiente' ? 'pendiente' : 'parcialmente pagada' }}</b> de 
            <b>S/ {{ number_format(abs($cuota->mora->monto_mora_calculado), 2) }}</b>.
            <br>
            Si solo registra el pago de la cuota, la mora seguirá pendiente.
        </div>
    @endif
@endif
