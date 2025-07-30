<x-filament-panels::page>

    <div class="space-y-6">
        {{-- Información del Grupo --}}
        <div class="bg-white rounded-lg shadow p-4 md:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                {{-- Pagos Aprobados --}}
                <div class="flex flex-col items-center justify-center p-2 bg-blue-50 rounded">
                    <div class="text-lg font-semibold text-blue-700 flex items-center gap-1">
                        <x-heroicon-o-check-circle class="w-5 h-5" />
                        {{ $grupo->prestamos->sum(fn($p) => $p->cuotasGrupales->sum(fn($c) => $c->pagos->where('estado_pago', 'aprobado')->count())) }}
                    </div>
                    <span class="text-xs text-blue-900 font-medium">Aprobados</span>
                </div>

                {{-- Pagos Pendientes --}}
                <div class="flex flex-col items-center justify-center p-2 bg-yellow-50 rounded">
                    <div class="text-lg font-semibold text-yellow-700 flex items-center gap-1">
                        <x-heroicon-o-clock class="w-5 h-5" />
                        {{ $grupo->prestamos->sum(fn($p) => $p->cuotasGrupales->sum(fn($c) => $c->pagos->where('estado_pago', 'Pendiente')->count())) }}
                    </div>
                    <span class="text-xs text-yellow-900 font-medium">Pendientes</span>
                </div>

                {{-- Pagos Rechazados --}}
                <div class="flex flex-col items-center justify-center p-2 bg-red-50 rounded">
                    <div class="text-lg font-semibold text-red-700 flex items-center gap-1">
                        <x-heroicon-o-x-circle class="w-5 h-5" />
                        {{
                            $grupo->prestamos->sum(fn($p) =>
                                $p->cuotasGrupales->sum(fn($c) =>
                                    $c->pagos->filter(function($pago) {
                                        return strtolower($pago->estado_pago) === 'rechazado';
                                    })->count()
                                )
                            )
                        }}
                    </div>
                    <span class="text-xs text-red-900 font-medium">Rechazados</span>
                </div>

                {{-- Monto Total Pagado --}}
                <div class="flex flex-col items-center justify-center p-2 bg-green-50 rounded">
                    <div class="text-lg font-semibold text-green-700 flex items-center gap-1">
                        <x-heroicon-o-currency-dollar class="w-5 h-5" />
                        {{
                            'S/. ' . number_format(
                                $grupo->prestamos->sum(fn($p) => $p->cuotasGrupales->sum(fn($c) => $c->pagos->where('estado_pago', 'aprobado')->sum('monto_pagado'))), 2
                            )
                        }}
                    </div>
                    <span class="text-xs text-green-900 font-medium">Total Pagado</span>
                </div>

                {{-- Monto Mora Pagada --}}
                <div class="flex flex-col items-center justify-center p-2 bg-purple-50 rounded">
                    <div class="text-lg font-semibold text-purple-700 flex items-center gap-1">
                        <x-heroicon-o-exclamation-circle class="w-5 h-5" />
                        {{
                            'S/. ' . number_format(
                                $grupo->prestamos->sum(fn($p) => $p->cuotasGrupales->sum(fn($c) => $c->pagos->where('estado_pago', 'aprobado')->sum('monto_mora_pagada'))), 2
                            )
                        }}
                    </div>
                    <span class="text-xs text-purple-900 font-medium">Mora Pagada</span>
                </div>

            {{-- Saldo Pendiente (incluye mora) --}}
            <div class="flex flex-col items-center justify-center p-2 bg-orange-50 rounded">
                <div class="text-lg font-semibold text-orange-700 flex items-center gap-1">
                    <x-heroicon-o-calculator class="w-5 h-5" />
                    {{
                        'S/. ' . number_format((function($grupo) {
                            $montoDevolver = 0;
                            $moraAcumulada = 0;
                            $montoPagado = 0;
                            foreach ($grupo->prestamos as $prestamo) {
                                foreach ($prestamo->cuotasGrupales as $cuota) {
                                    if (strtolower($cuota->estado_cuota_grupal ?? '') !== 'anulada') {
                                        $montoDevolver += floatval($cuota->monto_cuota_grupal);
                                    }
                                    if ($cuota->mora && strtolower($cuota->estado_cuota_grupal ?? '') !== 'cancelada') {
                                        $moraAcumulada += abs($cuota->mora->monto_mora_calculado);
                                    }
                                    $montoPagado += $cuota->pagos->where('estado_pago', 'aprobado')->sum('monto_pagado');
                                }
                            }
                            $saldo = ($montoDevolver + $moraAcumulada) - $montoPagado;
                            return max($saldo, 0);
                        })($grupo), 2)
                    }}
                </div>
                <span class="text-xs text-orange-900 font-medium">Saldo Pendiente</span>
            </div>
            </div>
        </div>

        {{-- Tabla de Pagos --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
