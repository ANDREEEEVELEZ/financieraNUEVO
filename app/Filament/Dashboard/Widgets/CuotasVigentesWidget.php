<?php

namespace App\Filament\Dashboard\Widgets;

use App\Models\CuotasGrupales;
use App\Models\Prestamo;
use App\Contracts\CacheServiceInterface;
use App\Contracts\SaldoCuotaServiceInterface;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class CuotasVigentesWidget extends BaseWidget
{
    protected static ?string $heading = 'Cuotas por Vencer';

    protected static bool $isLazy = true;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = request()->user();
        $asesor = $user?->hasRole('Asesor') ? app(CacheServiceInterface::class)->getAsesorByUserId($user->id) : null;

        return $table
            ->query(
                CuotasGrupales::query()
                    ->with(['prestamo.grupo.asesor.persona', 'mora'])
                    // withSum precomputa las sumas que SaldoCuotaService necesita,
                    // evitando una query adicional por fila en "Saldo" (D8, no N+1).
                    ->withSum(['pagos as monto_pagado_aprobado_sum' => function ($q) {
                        $q->where('estado_pago', 'aprobado');
                    }], 'monto_pagado')
                    ->withSum(['pagos as monto_mora_pagada_aprobado_sum' => function ($q) {
                        $q->where('estado_pago', 'aprobado');
                    }], 'monto_mora_pagada')
                    ->join('prestamos', 'cuotas_grupales.prestamo_id', '=', 'prestamos.id')
                    ->leftJoin('grupos', 'prestamos.grupo_id', '=', 'grupos.id')
                    ->whereIn('prestamos.estado', Prestamo::ESTADOS_ACTIVOS)
                    ->when($asesor, fn($q) => $q->where('grupos.asesor_id', $asesor->id))
                    ->where('cuotas_grupales.estado_pago', '!=', 'pagado')
                    ->whereBetween('cuotas_grupales.fecha_vencimiento', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
                    ->select('cuotas_grupales.*')
                    ->orderBy('cuotas_grupales.fecha_vencimiento', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('prestamo.grupo.nombre_grupo')
                    ->label('Grupo')
                    ->searchable()
                    ->sortable()
                    ->limit(20),

                Tables\Columns\TextColumn::make('numero_cuota')
                    ->label('Cuota #')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('monto_cuota_grupal')
                    ->label('Monto')
                    ->money('PEN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_vencimiento')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn($record) => $record->fecha_vencimiento->isPast() ? 'danger' : ($record->fecha_vencimiento->isToday() ? 'warning' : 'success'))
                    ->weight(fn($record) => $record->fecha_vencimiento->isToday() ? 'bold' : 'normal'),

                Tables\Columns\TextColumn::make('estado_pago')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state) => match (strtolower($state)) {
                        'pendiente' => 'warning',
                        'parcial' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('saldo_pendiente')
                    ->label('Saldo')
                    ->money('PEN')
                    ->sortable()
                    ->getStateUsing(fn ($record) => app(SaldoCuotaServiceInterface::class)->saldoTotal($record)),

                Tables\Columns\TextColumn::make('prestamo.grupo.asesor.persona.nombre')
                    ->label('Asesor')
                    ->formatStateUsing(function ($state, $record) {
                        $asesor = $record->prestamo?->grupo?->asesor;
                        if (!$asesor?->persona)
                            return '-';
                        return $asesor->persona->nombre . ' ' . $asesor->persona->apellidos;
                    })
                    ->visible(fn() => !request()->user()?->hasRole('Asesor'))
                    ->limit(18),
            ])
            ->emptyStateHeading('Sin cuotas próximas a vencer')
            ->emptyStateDescription('No hay cuotas pendientes para los próximos 7 días.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10);
    }
}
