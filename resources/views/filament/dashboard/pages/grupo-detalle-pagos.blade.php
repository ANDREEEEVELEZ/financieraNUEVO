<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Información del Grupo --}}
        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">

                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">
                        {{ $grupo->prestamos->sum(function($p) {
                            return $p->cuotasGrupales->sum(function($c) {
                                return $c->pagos->where('estado_pago', 'aprobado')->count();
                            });
                        }) }}
                    </div>
                    <p class="text-sm text-gray-600">Pagos Aprobados</p>
                </div>

                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600">
                        {{ $grupo->prestamos->sum(function($p) {
                            return $p->cuotasGrupales->sum(function($c) {
                                return $c->pagos->where('estado_pago', 'Pendiente')->count();
                            });
                        }) }}
                    </div>
                    <p class="text-sm text-gray-600">Pagos Pendientes</p>
                </div>

                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">
                        {{ $grupo->prestamos->sum(function($p) {
                            return $p->cuotasGrupales->sum(function($c) {
                                return $c->pagos->where('estado_pago', 'rechazado')->count();
                            });
                        }) }}
                    </div>
                    <p class="text-sm text-gray-600">Pagos Rechazados</p>
                </div>

            </div>
        </div>

        {{-- Tabla de Pagos --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
