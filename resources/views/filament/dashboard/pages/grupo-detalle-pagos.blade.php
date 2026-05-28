<x-filament-panels::page>

    <div class="space-y-6">
        {{-- Información del Grupo --}}
        <div class="bg-white rounded-lg shadow p-4 md:p-6">
            {{-- Todas las tarjetas en dos columnas: 3 de un lado y 3 del otro --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                {{-- Columna izquierda: 3 tarjetas --}}
                <div class="space-y-4">
                    {{-- Pagos Aprobados --}}
                    <div class="flex flex-col items-center justify-center p-3 bg-blue-50 rounded-lg border border-blue-100 min-h-[80px]">
                        <div class="text-lg font-bold text-blue-700 flex items-center gap-1 mb-1">
                            <x-heroicon-o-check-circle class="w-5 h-5" />
                            {{ $prestamo->cuotasGrupales->sum(fn($c) => $c->pagos->where('estado_pago', 'aprobado')->count()) }}
                        </div>
                        <span class="text-xs text-blue-900 font-medium text-center">Aprobados</span>
                    </div>

                    {{-- Pagos Pendientes --}}
                    <div class="flex flex-col items-center justify-center p-3 bg-yellow-50 rounded-lg border border-yellow-100 min-h-[80px]">
                        <div class="text-lg font-bold text-yellow-700 flex items-center gap-1 mb-1">
                            <x-heroicon-o-clock class="w-5 h-5" />
                            {{ $prestamo->cuotasGrupales->sum(fn($c) => $c->pagos->where('estado_pago', 'Pendiente')->count()) }}
                        </div>
                        <span class="text-xs text-yellow-900 font-medium text-center">Pendientes</span>
                    </div>

                    {{-- Pagos Rechazados --}}
                    <div class="flex flex-col items-center justify-center p-3 bg-red-50 rounded-lg border border-red-100 min-h-[80px]">
                        <div class="text-lg font-bold text-red-700 flex items-center gap-1 mb-1">
                            <x-heroicon-o-x-circle class="w-5 h-5" />
                            {{
                                $prestamo->cuotasGrupales->sum(fn($c) =>
                                    $c->pagos->filter(function($pago) {
                                        return strtolower($pago->estado_pago) === 'rechazado';
                                    })->count()
                                )
                            }}
                        </div>
                        <span class="text-xs text-red-900 font-medium text-center">Rechazados</span>
                    </div>
                </div>

                {{-- Columna derecha: 3 tarjetas --}}
                <div class="space-y-4">
                    {{-- Monto Total Pagado --}}
                    <div class="flex flex-col items-center justify-center p-3 bg-green-50 rounded-lg border border-green-100 min-h-[80px]">
                        <div class="text-lg font-bold text-green-700 flex items-center gap-1 mb-1">
                            <x-heroicon-o-currency-dollar class="w-5 h-5" />
                            {{
                                'S/. ' . number_format(
                                    $prestamo->cuotasGrupales->sum(fn($c) => $c->pagos->where('estado_pago', 'aprobado')->sum('monto_pagado')),
                                    2
                                )
                            }}
                        </div>
                        <span class="text-xs text-green-900 font-medium text-center">Total Pagado</span>
                    </div>

                    {{-- Monto Mora Pagada --}}
                    <div class="flex flex-col items-center justify-center p-3 bg-purple-50 rounded-lg border border-purple-100 min-h-[80px]">
                        <div class="text-lg font-bold text-purple-700 flex items-center gap-1 mb-1">
                            <x-heroicon-o-exclamation-circle class="w-5 h-5" />
                            {{
                                'S/. ' . number_format(
                                    $prestamo->cuotasGrupales->sum(fn($c) => $c->pagos->where('estado_pago', 'aprobado')->sum('monto_mora_pagada')),
                                    2
                                )
                            }}
                        </div>
                        <span class="text-xs text-purple-900 font-medium text-center">Mora Pagada</span>
                    </div>

                    {{-- Saldo Pendiente Total --}}
                    <div class="flex flex-col items-center justify-center p-3 bg-orange-50 rounded-lg border-2 border-orange-200 min-h-[80px]">
                        <div class="text-lg font-bold text-orange-700 flex items-center gap-1 mb-1">
                            <x-heroicon-o-calculator class="w-5 h-5" />
                            {{
                                'S/. ' . number_format((function($prestamo) {
                                    $saldoTotal = 0;
                                    foreach ($prestamo->cuotasGrupales as $cuota) {
                                        if (strtolower($cuota->estado_cuota_grupal ?? '') !== 'anulada') {
                                            $saldoTotal += $cuota->saldoPendiente();
                                        }
                                    }
                                    return max($saldoTotal, 0);
                                })($prestamo), 2)
                            }}
                        </div>
                        <span class="text-xs text-orange-900 font-medium text-center">Saldo Pendiente</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabla de Pagos --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
