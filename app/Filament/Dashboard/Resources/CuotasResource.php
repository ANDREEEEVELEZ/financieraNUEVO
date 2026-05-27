<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\CuotasResource\Pages;
use App\Filament\Dashboard\Resources\PagoResource;
use App\Models\CuotasGrupales;
use App\Models\Prestamo;
use App\Contracts\CacheServiceInterface;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CuotasResource extends Resource
{
    protected static ?string $model = CuotasGrupales::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Cuotas';
    protected static ?string $modelLabel = 'Cuota';
    protected static ?string $pluralModelLabel = 'Cuotas';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $asesor = $user?->hasRole('Asesor') ? app(CacheServiceInterface::class)->getAsesorByUserId($user->id) : null;

        return $table
            ->query(
                CuotasGrupales::query()
                    ->with(['prestamo.grupo.asesor.persona', 'mora'])
                    ->join('prestamos', 'cuotas_grupales.prestamo_id', '=', 'prestamos.id')
                    ->leftJoin('grupos', 'prestamos.grupo_id', '=', 'grupos.id')
                    ->whereIn('prestamos.estado', Prestamo::ESTADOS_ACTIVOS)
                    ->when($asesor, fn($q) => $q->where('grupos.asesor_id', $asesor->id))
                    ->select('cuotas_grupales.*')
                    ->orderBy('cuotas_grupales.fecha_vencimiento', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('prestamo.grupo.nombre_grupo')
                    ->label('Grupo')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('numero_cuota')
                    ->label('Cuota #')
                    ->alignCenter()
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha_vencimiento')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn($record) => match (true) {
                        $record->fecha_vencimiento->isPast() && $record->estado_pago !== 'pagado' => 'danger',
                        $record->fecha_vencimiento->isToday() => 'warning',
                        default => 'success',
                    })
                    ->weight(fn($record) => $record->fecha_vencimiento->isToday() ? 'bold' : 'normal'),

                Tables\Columns\TextColumn::make('monto_cuota_grupal')
                    ->label('Monto Cuota')
                    ->money('PEN')
                    ->sortable()
                    ->alignRight(),

                Tables\Columns\TextColumn::make('mora.monto_mora_calculado')
                    ->label('Mora')
                    ->money('PEN')
                    ->alignRight()
                    ->color('danger')
                    ->default('-')
                    ->formatStateUsing(fn($state) => $state && $state > 0 ? 'S/. ' . number_format(abs($state), 2) : '-'),

                Tables\Columns\TextColumn::make('saldo_pendiente')
                    ->label('Saldo')
                    ->money('PEN')
                    ->sortable()
                    ->alignRight()
                    ->color(fn($record) => $record->saldo_pendiente > 0 ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('estado_cuota_grupal')
                    ->label('Estado Cuota')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'vigente' => 'success',
                        'mora' => 'danger',
                        'cancelada' => 'gray',
                        'pendiente' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'vigente' => 'Vigente',
                        'mora' => 'En Mora',
                        'cancelada' => 'Cancelada',
                        'pendiente' => 'Pendiente',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('estado_pago')
                    ->label('Estado Pago')
                    ->badge()
                    ->color(fn(string $state) => match (strtolower($state)) {
                        'pendiente' => 'warning',
                        'parcial' => 'info',
                        'pagado' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => match (strtolower($state)) {
                        'pendiente' => 'Pendiente',
                        'parcial' => 'Parcial',
                        'pagado' => 'Pagado',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('prestamo.grupo.asesor.persona.nombre')
                    ->label('Asesor')
                    ->formatStateUsing(function ($state, $record) {
                        $asesor = $record->prestamo?->grupo?->asesor;
                        if (!$asesor?->persona) {
                            return '-';
                        }
                        return trim($asesor->persona->nombre . ' ' . $asesor->persona->apellidos);
                    })
                    ->visible(fn() => !Auth::user()?->hasRole('Asesor'))
                    ->searchable(false),
            ])
            ->filters([
                SelectFilter::make('estado_cuota_grupal')
                    ->label('Estado Cuota')
                    ->options([
                        'vigente' => 'Vigente',
                        'mora' => 'En Mora',
                        'cancelada' => 'Cancelada',
                        'pendiente' => 'Pendiente',
                    ]),

                SelectFilter::make('estado_pago')
                    ->label('Estado Pago')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'parcial' => 'Parcial',
                        'pagado' => 'Pagado',
                    ]),

                Filter::make('fecha_vencimiento')
                    ->label('Rango de Vencimiento')
                    ->form([
                        DatePicker::make('desde')->label('Desde')->displayFormat('d/m/Y'),
                        DatePicker::make('hasta')->label('Hasta')->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'], fn($q, $date) => $q->whereDate('fecha_vencimiento', '>=', $date))
                            ->when($data['hasta'], fn($q, $date) => $q->whereDate('fecha_vencimiento', '<=', $date));
                    }),

                Filter::make('proximas_a_vencer')
                    ->label('Próximas a vencer (7 días)')
                    ->query(fn(Builder $query) => $query->whereBetween(
                        'fecha_vencimiento',
                        [now()->startOfDay(), now()->addDays(7)->endOfDay()]
                    ))
                    ->toggle(),

                Filter::make('vencidas')
                    ->label('Vencidas sin pagar')
                    ->query(fn(Builder $query) => $query
                        ->where('fecha_vencimiento', '<', now()->startOfDay())
                        ->where('estado_pago', '!=', 'pagado')
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('ver_pagos')
                    ->label('Ver Pagos')
                    ->icon('heroicon-m-credit-card')
                    ->color('primary')
                    ->url(function ($record) {
                        $grupo = $record->prestamo?->grupo;
                        $prestamo = $record->prestamo;
                        if (!$grupo || !$prestamo) {
                            return null;
                        }
                        return PagoResource::getUrl('grupo-detalle', [
                            'grupo' => $grupo->id,
                            'prestamo' => $prestamo->id,
                        ]);
                    })
                    ->visible(fn($record) => $record->prestamo?->grupo !== null),
            ])
            ->bulkActions([])
            ->defaultSort('fecha_vencimiento', 'asc')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCuotas::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
