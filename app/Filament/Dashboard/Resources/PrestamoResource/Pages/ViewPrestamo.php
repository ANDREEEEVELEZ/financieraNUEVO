<?php

namespace App\Filament\Dashboard\Resources\PrestamoResource\Pages;

use App\Filament\Dashboard\Resources\PrestamoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ViewPrestamo extends ViewRecord
{
    protected static string $resource = PrestamoResource::class;

    public function getTitle(): string
    {
        $titulo = 'Ver Préstamo';
        
        // Mostrar el estado en el título
        if ($this->record) {
            return $titulo . ' - Estado: ' . $this->record->estado;
        }
        
        return $titulo;
    }

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // Validar permisos antes de mostrar el formulario
        $user = Auth::user();
        
        if ($user && $user->roles->pluck('name')->contains('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            $esCreador = $asesor && $this->record->grupo && $this->record->grupo->asesor_id == $asesor->id;
            
            if (!$esCreador) {
                Notification::make()
                    ->title('Sin permisos')
                    ->body('No tienes permisos para ver este préstamo porque no eres el asesor que lo creó.')
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
        $actions = [];

        // Botón para editar solo si el estado es Pendiente Y NO es retanqueo
        if ($this->record->estado === 'Pendiente' && !$this->record->es_retanqueo) {
            $actions[] = Actions\EditAction::make()
                ->icon('heroicon-m-pencil-square')
                ->label('Editar');
        }

        // Solo mostrar botones de aprobar/rechazar para roles mayores, préstamos pendientes Y que NO sean retanqueos
        if ($user && $user->roles->pluck('name')->intersect(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])->isNotEmpty() && 
            $this->record->estado === 'Pendiente' && !$this->record->es_retanqueo) {
            
            // Botón Aprobar
            $actions[] = Actions\Action::make('aprobar')
                ->label('Aprobar')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar Préstamo')
                ->modalDescription('¿Está seguro de que desea aprobar este préstamo?')
                ->modalSubmitActionLabel('Sí, Aprobar')
                ->action(function () {
                    $this->record->aprobar();
                    
                    Notification::make()
                        ->title('Préstamo Aprobado')
                        ->body('El préstamo ha sido aprobado exitosamente.')
                        ->success()
                        ->send();
                    
                    // Recargar la página para reflejar los cambios
                    return redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                });

            // Botón Rechazar
            $actions[] = Actions\Action::make('rechazar')
                ->label('Rechazar')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Rechazar Préstamo')
                ->modalDescription('¿Está seguro de que desea rechazar este préstamo?')
                ->modalSubmitActionLabel('Sí, Rechazar')
                ->action(function () {
                    $this->record->rechazar();
                    
                    Notification::make()
                        ->title('Préstamo Rechazado')
                        ->body('El préstamo ha sido rechazado.')
                        ->danger()
                        ->send();
                    
                    // Recargar la página para reflejar los cambios
                    return redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                });
        }

        // Botones para préstamos APROBADOS que NO sean retanqueos
        if ($user && $user->roles->pluck('name')->intersect(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])->isNotEmpty() && 
            $this->record->estado === 'Aprobado' && !$this->record->es_retanqueo) {
            
            // Botón Ejecutar
            $actions[] = Actions\Action::make('ejecutar')
                ->label('Ejecutar Préstamo')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('¿Ejecutar este préstamo?')
                ->modalDescription('Al ejecutar el préstamo, se confirma que los contratos están firmados y se procederá con el desembolso.')
                ->modalSubmitActionLabel('Sí, ejecutar')
                ->action(function () {
                    if (!$this->record->fecha_desembolso) {
                        Notification::make()
                            ->title('Error al ejecutar el préstamo')
                            ->body('Debe especificar la fecha de desembolso antes de ejecutar el préstamo.')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    if ($this->record->ejecutar()) {
                        Notification::make()
                            ->title('Préstamo ejecutado correctamente')
                            ->success()
                            ->send();
                        
                        // Recargar la página para reflejar los cambios
                        return redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                    }
                });

            // Botón Rechazar (también disponible desde estado Aprobado)
            $actions[] = Actions\Action::make('rechazar_aprobado')
                ->label('Rechazar Préstamo')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Rechazar Préstamo Aprobado')
                ->modalDescription('¿Está seguro de que desea rechazar este préstamo aprobado? Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, Rechazar')
                ->action(function () {
                    if ($this->record->rechazar()) {
                        Notification::make()
                            ->title('Préstamo Rechazado')
                            ->body('El préstamo ha sido rechazado exitosamente.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Error al rechazar')
                            ->body('No se pudo rechazar el préstamo. Verifique el estado actual.')
                            ->danger()
                            ->send();
                    }
                    
                    // Recargar la página para reflejar los cambios
                    return redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                });
        }

        // Acción especial para retanqueos: redirigir al módulo de Retanqueos
        if ($this->record->es_retanqueo && $this->record->estado === 'Pendiente') {
            $actions[] = Actions\Action::make('gestionar_retanqueo')
                ->label('Gestionar en Retanqueos')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('warning')
                ->url(function() {
                    if ($this->record->retanqueoComoNuevo) {
                        return route('filament.dashboard.resources.retanqueos.view', $this->record->retanqueoComoNuevo->id);
                    }
                    return route('filament.dashboard.resources.retanqueos.index');
                })
                ->tooltip('Este préstamo es un retanqueo y debe gestionarse desde el módulo de Retanqueos');
        }

        // Botón para imprimir contrato si está en estado válido
        if ($this->record->grupo_id !== null && 
            in_array(strtolower($this->record->estado), ['aprobado', 'activo', 'parcialmente_retanqueado', 'finalizado'])) {
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
}
