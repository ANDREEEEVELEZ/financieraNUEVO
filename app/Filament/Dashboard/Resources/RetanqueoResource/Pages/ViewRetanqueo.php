<?php

namespace App\Filament\Dashboard\Resources\RetanqueoResource\Pages;

use App\Filament\Dashboard\Resources\RetanqueoResource;
use App\Services\RetanqueoService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Notifications\Notification;

class ViewRetanqueo extends ViewRecord
{
    protected static string $resource = RetanqueoResource::class;

    protected function getHeaderActions(): array
    {
        $user = request()->user();
        $record = $this->record;
        
        $actions = [];

        // Acción de editar (solo para solicitudes pendientes)
        if ($record->esSolicitudPendiente() && static::getResource()::canEdit($record)) {
            $actions[] = Actions\EditAction::make()
                ->icon('heroicon-m-pencil-square');
        }

        // Acciones para jefes y super_admin
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            
            // Aprobar solicitud
            if ($record->esSolicitudPendiente()) {
                $actions[] = Actions\Action::make('aprobar')
                    ->label('Aprobar Retanqueo')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Retanqueo')
                    ->modalDescription('¿Está seguro de que desea aprobar este retanqueo?')
                    ->action(function () {
                        try {
                            $retanqueoService = new RetanqueoService();
                            $retanqueoService->aprobarRetanqueo($this->record->id);
                            
                            Notification::make()
                                ->title('Retanqueo Aprobado')
                                ->body('El retanqueo ha sido aprobado exitosamente.')
                                ->success()
                                ->send();

                            $this->refreshFormData(['estado_retanqueo']);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body('Error al aprobar el retanqueo: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    });

                $actions[] = Actions\Action::make('rechazar')
                    ->label('Rechazar Retanqueo')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar Retanqueo')
                    ->modalDescription('¿Está seguro de que desea rechazar este retanqueo?')
                    ->action(function () {
                        try {
                            $retanqueoService = new RetanqueoService();
                            $retanqueoService->rechazarRetanqueo($this->record->id);
                            
                            Notification::make()
                                ->title('Retanqueo Rechazado')
                                ->body('El retanqueo ha sido rechazado.')
                                ->success()
                                ->send();

                            $this->refreshFormData(['estado_retanqueo']);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body('Error al rechazar el retanqueo: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    });
            }

            // Ejecutar retanqueo
            if ($record->estaAprobado()) {
                $actions[] = Actions\Action::make('ejecutar')
                    ->label('Ejecutar Retanqueo')
                    ->icon('heroicon-m-play')
                    ->color('primary')
                    ->form([
                        \Filament\Forms\Components\Section::make('Información de Desembolso')
                            ->description('Datos bancarios para el desembolso del nuevo préstamo')
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('titular_cuenta_desembolso')
                                    ->label('Titular de la Cuenta a Desembolsar')
                                    ->prefixIcon('heroicon-o-user')
                                    ->placeholder('Ingrese el nombre del titular de la cuenta')
                                    ->maxLength(255)
                                    ->helperText('💳 Nombre completo del titular (solo letras y espacios)')
                                    ->rule('regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/')
                                    ->rule('min:3')
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state && !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $state)) {
                                            $set('titular_cuenta_desembolso', '');
                                        }
                                    }),

                                \Filament\Forms\Components\TextInput::make('numero_cuenta_desembolso')
                                    ->label('Número de la Cuenta a Desembolsar')
                                    ->prefixIcon('heroicon-o-credit-card')
                                    ->placeholder('Ingrese el número de cuenta (14 dígitos)')
                                    ->maxLength(14)
                                    ->minLength(14)
                                    ->helperText('🏦 Número de cuenta bancaria (exactamente 14 números)')
                                    ->rule('regex:/^[0-9]{14}$/')
                                    ->numeric()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        if ($state) {
                                            // Solo permitir números
                                            $cleaned = preg_replace('/[^0-9]/', '', $state);
                                            if (strlen($cleaned) > 14) {
                                                $cleaned = substr($cleaned, 0, 14);
                                            }
                                            $set('numero_cuenta_desembolso', $cleaned);
                                        }
                                    }),
                            ])
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Ejecutar Retanqueo')
                    ->modalDescription('Complete la información de desembolso y confirme la ejecución del retanqueo. Esta acción creará el nuevo préstamo.')
                    ->modalSubmitActionLabel('Ejecutar Retanqueo')
                    ->action(function (array $data) {
                        try {
                            $retanqueoService = new RetanqueoService();
                            
                            // SEGURO: Preparar datos de cuenta con validación
                            $datosCuenta = [];
                            if (!empty($data['titular_cuenta_desembolso'])) {
                                $datosCuenta['titular_cuenta_desembolso'] = trim($data['titular_cuenta_desembolso']);
                            }
                            if (!empty($data['numero_cuenta_desembolso'])) {
                                $datosCuenta['numero_cuenta_desembolso'] = trim($data['numero_cuenta_desembolso']);
                            }
                            
                            $retanqueoService->ejecutarRetanqueo($this->record->id, $datosCuenta);
                            
                            Notification::make()
                                ->title('Retanqueo Ejecutado')
                                ->body('El retanqueo ha sido ejecutado exitosamente. Se ha creado el nuevo préstamo.')
                                ->success()
                                ->send();

                            $this->refreshFormData(['estado_retanqueo', 'prestamo_nuevo_id']);
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error')
                                ->body('Error al ejecutar el retanqueo: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    });
            }
        }

        return $actions;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información General')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('id')
                                    ->label('ID Retanqueo'),
                                
                                TextEntry::make('estado_retanqueo')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'solicitud_pendiente' => 'warning',
                                        'aprobado' => 'success',
                                        'ejecutado' => 'primary',
                                        'rechazado' => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'solicitud_pendiente' => 'Pendiente',
                                        'aprobado' => 'Aprobado',
                                        'ejecutado' => 'Ejecutado',
                                        'rechazado' => 'Rechazado',
                                        default => $state,
                                    }),

                                TextEntry::make('created_at')
                                    ->label('Fecha Solicitud')
                                    ->dateTime('d/m/Y H:i'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('fecha_aceptacion')
                                    ->label('Fecha Aprobación')
                                    ->date('d/m/Y')
                                    ->placeholder('No aprobado'),

                                TextEntry::make('cantidad_cuotas_nuevo')
                                    ->label('Cuotas Nuevo Préstamo'),
                            ]),
                    ]),

                Section::make('Información del Grupo y Préstamo Antiguo')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('prestamoAntiguo.grupo.nombre_grupo')
                                    ->label('Grupo'),

                                TextEntry::make('prestamoAntiguo.grupo.asesor.persona.nombre')
                                    ->label('Asesor')
                                    ->getStateUsing(function ($record) {
                                        $asesor = $record->prestamoAntiguo?->grupo?->asesor;
                                        if ($asesor && $asesor->persona) {
                                            return $asesor->persona->nombre . ' ' . $asesor->persona->apellidos;
                                        }
                                        return 'Sin asesor';
                                    }),

                                TextEntry::make('prestamoAntiguo.monto_prestado_total')
                                    ->label('Monto Prestado Original')
                                    ->money('PEN'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('prestamoAntiguo.fecha_prestamo')
                                    ->label('Fecha Préstamo Original')
                                    ->date('d/m/Y'),

                                TextEntry::make('prestamoAntiguo.frecuencia')
                                    ->label('Frecuencia Original')
                                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                            ]),
                    ]),

                Section::make('Montos del Retanqueo')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('monto_retanqueo')
                                    ->label('Nuevo Préstamo')
                                    ->money('PEN')
                                    ->color('primary'),

                                TextEntry::make('monto_usado_para_cubrir_antiguo')
                                    ->label('Cobertura Antiguo')
                                    ->money('PEN')
                                    ->color('success'),

                                TextEntry::make('monto_desembolsar')
                                    ->label('A Entregar')
                                    ->money('PEN')
                                    ->color('warning'),

                                TextEntry::make('saldo_restante_prestamo_antiguo')
                                    ->label('Saldo Restante')
                                    ->money('PEN')
                                    ->color('danger'),
                            ]),
                    ]),

                Section::make('Participantes del Retanqueo')
                    ->schema([
                        RepeatableEntry::make('retanqueosIndividuales')
                            ->label('')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextEntry::make('cliente.persona.nombre')
                                            ->label('Nombre')
                                            ->getStateUsing(function ($record) {
                                                return $record->cliente->persona->nombre . ' ' . $record->cliente->persona->apellidos;
                                            }),

                                        TextEntry::make('participacion_tipo')
                                            ->label('Participación')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'retanquea' => 'success',
                                                'no_retanquea' => 'danger',
                                                'nueva' => 'info',
                                                default => 'gray',
                                            })
                                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                                'retanquea' => 'Retanquea',
                                                'no_retanquea' => 'No Retanquea',
                                                'nueva' => 'Cliente Nuevo',
                                                default => $state,
                                            }),

                                        TextEntry::make('monto_solicitado')
                                            ->label('Monto Solicitado')
                                            ->money('PEN'),

                                        TextEntry::make('aporte_cobertura')
                                            ->label('Aporte Cobertura')
                                            ->money('PEN'),

                                        TextEntry::make('monto_desembolsar')
                                            ->label('A Desembolsar')
                                            ->money('PEN'),

                                        TextEntry::make('estado_retanqueo_individual')
                                            ->label('Estado')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'propuesto' => 'warning',
                                                'aceptado' => 'success',
                                                'rechazado' => 'danger',
                                                default => 'gray',
                                            })
                                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                                'propuesto' => 'Propuesto',
                                                'aceptado' => 'Aceptado',
                                                'rechazado' => 'Rechazado',
                                                default => $state,
                                            }),
                                    ])
                            ])
                    ]),

                Section::make('Información del Nuevo Préstamo')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('prestamoNuevo.id')
                                    ->label('ID Nuevo Préstamo')
                                    ->placeholder('No ejecutado aún'),

                                TextEntry::make('prestamoNuevo.fecha_prestamo')
                                    ->label('Fecha Nuevo Préstamo')
                                    ->date('d/m/Y')
                                    ->placeholder('No ejecutado aún'),

                                TextEntry::make('prestamoNuevo.estado')
                                    ->label('Estado Nuevo Préstamo')
                                    ->badge()
                                    ->placeholder('No ejecutado aún'),
                            ]),
                    ])
                    ->visible(fn ($record) => $record->prestamoNuevo !== null),
            ]);
    }
}
