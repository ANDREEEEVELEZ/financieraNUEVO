<?php

namespace App\Filament\Dashboard\Widgets;

use App\Models\CuotasGrupales;
use App\Models\Asesor;
use App\Models\Prestamo;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class CuotasVigentesWidget extends BaseWidget
{
    protected static ?string $heading = 'Cuotas por Vencer';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CuotasGrupales::query()
                    ->with(['prestamo.grupo.asesor.persona', 'mora'])
                    ->whereHas('prestamo', function (Builder $q) {
                        $q->whereIn('estado', Prestamo::ESTADOS_ACTIVOS);

                        // Filtrar por asesor si el usuario es Asesor
                        $user = request()->user();
                        if ($user?->hasRole('Asesor')) {
                            $asesor = Asesor::where('user_id', $user->id)->first();
                            if ($asesor) {
                                $q->whereHas('grupo', fn($g) => $g->where('asesor_id', $asesor->id));
                            }
                        }
                    })
                    ->where('estado_pago', '!=', 'pagado')
                    ->whereBetween('fecha_vencimiento', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
                    ->orderBy('fecha_vencimiento', 'asc')
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
                    ->sortable(),

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
