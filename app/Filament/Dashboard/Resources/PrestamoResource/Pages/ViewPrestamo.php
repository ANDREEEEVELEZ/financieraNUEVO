<?php

namespace App\Filament\Dashboard\Resources\PrestamoResource\Pages;

use App\Filament\Dashboard\Resources\PrestamoResource;
use App\Models\AuditLog;
use App\Models\Prestamo;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ViewPrestamo extends ViewRecord
{
    protected static string $resource = PrestamoResource::class;

    public function getTitle(): string
    {
        $titulo = 'Ver Préstamo';
        if ($this->record) {
            return $titulo . ' - Estado: ' . $this->record->estado_visible;
        }
        return $titulo;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Validar que Asesor solo vea sus propios préstamos
        $user = Auth::user();
        if ($user?->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            $esCreador = $asesor && $this->record->grupo && $this->record->grupo->asesor_id == $asesor->id;

            if (!$esCreador) {
                Notification::make()
                    ->title('Sin permisos')
                    ->body('No tienes permisos para ver este préstamo.')
                    ->danger()
                    ->send();
                $this->redirect(static::getResource()::getUrl('index'));
                return;
            }
        }
    }

    protected function getHeaderActions(): array
    {
        $user = Auth::user();
        $record = $this->record;
        $actions = [];

        // ── Editar (solo Pendiente, no retanqueo) ──
        if ($record->estado === Prestamo::ESTADO_PENDIENTE && !$record->es_retanqueo) {
            $actions[] = Actions\EditAction::make()
                ->icon('heroicon-m-pencil-square')
                ->label('Editar');
        }

        // ── Aprobar (Pendiente → Aprobado) — JC/super_admin ──
        if ($user?->can('aprobar', $record) && !$record->es_retanqueo) {
            $actions[] = Actions\Action::make('aprobar')
                ->label('Aprobar')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar Préstamo')
                ->modalDescription('Al aprobar, el asesor podrá proceder con la firma del contrato.')
                ->modalSubmitActionLabel('Sí, Aprobar')
                ->action(function () {
                    $this->record->aprobar();
                    Notification::make()->title('Préstamo Aprobado')->success()->send();
                    return redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                });
        }

        // ── Firmar (Aprobado → Firmado) — Asesor ──
        if ($user?->can('firmar', $record) && !$record->es_retanqueo) {
            $actions[] = Actions\Action::make('firmar')
                ->label('Contrato Firmado')
                ->icon('heroicon-o-pencil-square')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('¿Confirmar firma de contrato?')
                ->modalDescription('El préstamo pasará a estado "Firmado".')
                ->modalSubmitActionLabel('Sí, firmado')
                ->action(function () {
                    if ($this->record->firmar()) {
                        Notification::make()->title('Contrato firmado')->success()->send();
                    } else {
                        Notification::make()->title('Error')->body('Verifique el estado.')->danger()->send();
                    }
                    return redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                });
        }

        // ── Desembolsar (Firmado → Activo) — JO ──
        if ($user?->can('desembolsar', $record) && !$record->es_retanqueo) {
            $actions[] = Actions\Action::make('desembolsar')
                ->label('Desembolsar')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('¿Desembolsar?')
                ->modalDescription('Se crearán las cuotas automáticamente. Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, desembolsar')
                ->action(function () {
                    if ($this->record->desembolsar($this->record->fecha_desembolso ?? now())) {
                        Notification::make()->title('Préstamo desembolsado')->success()->send();
                    } else {
                        Notification::make()->title('Error al desembolsar')->danger()->send();
                    }
                    return redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                });
        }

        // ── Rechazar (Pendiente/Aprobado → Rechazado) — JC/super_admin ──
        if ($user?->can('rechazar', $record) && !$record->es_retanqueo) {
            $actions[] = Actions\Action::make('rechazar')
                ->label('Rechazar')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('¿Rechazar este préstamo?')
                ->modalDescription('Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, Rechazar')
                ->action(function () {
                    $this->record->rechazar();
                    Notification::make()->title('Préstamo Rechazado')->danger()->send();
                    return redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                });
        }

        // ── Reformular (Rechazado → Reformulado) — JC ──
        if ($user?->can('reformular', $record) && !$record->es_retanqueo) {
            $actions[] = Actions\Action::make('reformular')
                ->label('Reformular')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->reformular();
                    Notification::make()->title('Préstamo reformulado')->success()->send();
                    return redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                });
        }

        // ── Gestionar en Retanqueos ──
        if ($record->es_retanqueo) {
            $actions[] = Actions\Action::make('gestionar_retanqueo')
                ->label('Gestionar en Retanqueos')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('warning')
                ->url(function () {
                    if ($this->record->retanqueoComoNuevo) {
                        return route('filament.dashboard.resources.retanqueos.view', $this->record->retanqueoComoNuevo->id);
                    }
                    return route('filament.dashboard.resources.retanqueos.index');
                })
                ->tooltip('Gestionar desde el módulo de Retanqueos');
        }

        // ── Imprimir Contrato / Cartilla ──
        if (
            $record->grupo_id !== null && in_array($record->estado, [
                Prestamo::ESTADO_APROBADO,
                Prestamo::ESTADO_FIRMADO,
                Prestamo::ESTADO_ACTIVO,
                Prestamo::ESTADO_AL_DIA,
                Prestamo::ESTADO_EN_MORA,
                Prestamo::ESTADO_FINALIZADO,
            ])
        ) {
            $actions[] = Actions\Action::make('imprimir_contrato')
                ->label('Imprimir Contrato')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn() => route('contratos.prestamo.imprimir', $this->record->id))
                ->openUrlInNewTab();

            $actions[] = Actions\Action::make('imprimir_cartilla')
                ->label('Imprimir Cartilla')
                ->icon('heroicon-o-identification')
                ->color('info')
                ->url(fn() => route('cartilla.prestamo.imprimir', $this->record->id))
                ->openUrlInNewTab();
        }

        return $actions;
    }

    /**
     * Infolist con Tab de Trazabilidad — timeline de audit_logs.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Tabs::make('Trazabilidad')
                    ->tabs([
                        // Tab 1: Datos del préstamo (el formulario existente se muestra por defecto)
                        Infolists\Components\Tabs\Tab::make('Información')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Infolists\Components\TextEntry::make('estado')
                                    ->label('Estado Actual')
                                    ->badge()
                                    ->color(fn($record) => $record->estado_badge_color),
                                Infolists\Components\TextEntry::make('monto_prestado_total')
                                    ->label('Monto Prestado')
                                    ->money('PEN'),
                                Infolists\Components\TextEntry::make('monto_devolver')
                                    ->label('Monto a Devolver')
                                    ->money('PEN'),
                                Infolists\Components\TextEntry::make('cantidad_cuotas')
                                    ->label('Nº Cuotas'),
                                Infolists\Components\TextEntry::make('fecha_prestamo')
                                    ->label('Fecha Préstamo')
                                    ->date('d/m/Y'),
                                Infolists\Components\TextEntry::make('fecha_desembolso')
                                    ->label('Fecha Desembolso')
                                    ->date('d/m/Y')
                                    ->placeholder('Sin desembolso'),
                            ])->columns(3),

                        // Tab 2: Historial de Auditoría
                        Infolists\Components\Tabs\Tab::make('Historial')
                            ->icon('heroicon-o-clock')
                            ->badge(fn($record) => AuditLog::delModelo(Prestamo::class, $record->id)->count() ?: null)
                            ->schema([
                                Infolists\Components\RepeatableEntry::make('auditLogs')
                                    ->label('')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('accion')
                                            ->label('Acción')
                                            ->badge()
                                            ->color(fn(string $state) => match ($state) {
                                                'aprobar' => 'success',
                                                'firmar' => 'info',
                                                'desembolsar' => 'success',
                                                'rechazar' => 'danger',
                                                'reformular' => 'warning',
                                                'cancelar' => 'danger',
                                                'reducir_monto' => 'warning',
                                                'revertir_pago' => 'warning',
                                                default => 'gray',
                                            }),
                                        Infolists\Components\TextEntry::make('user.name')
                                            ->label('Usuario'),
                                        Infolists\Components\TextEntry::make('created_at')
                                            ->label('Fecha')
                                            ->dateTime('d/m/Y H:i'),
                                        Infolists\Components\TextEntry::make('motivo')
                                            ->label('Motivo')
                                            ->placeholder('Sin motivo registrado')
                                            ->columnSpan(2),
                                    ])->columns(4),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
