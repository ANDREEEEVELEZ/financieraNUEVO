<?php
namespace App\Filament\Dashboard\Resources\AsesorResource\Pages;
use App\Filament\Dashboard\Resources\AsesorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditAsesor extends EditRecord
{
    protected static string $resource = AsesorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
            ->icon('heroicon-o-trash')
                ->action(function () {
                    // Buscar grupos asignados a este asesor
                    $grupos = $this->record->grupos()->get();
                    if ($grupos->count() > 0) {
                        $cantidad = $grupos->count();
                        $nombreAsesor = $this->record->persona ? $this->record->persona->nombre . ' ' . $this->record->persona->apellidos : '';

                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('¡Atención!')
                            ->body('No puedes eliminar al asesor hasta reasignar sus ' . $cantidad . ' grupo(s). Por favor, reasigna todos los grupos a otro asesor antes de continuar.')
                            ->persistent()
                            ->send();
                        // Redirigir a la lista de grupos filtrando por nombre de asesor
                        return redirect()->to(route('filament.dashboard.resources.grupos.index', ['tableFilters[asesor][value]' => $nombreAsesor]));
                    }

                    // Si no tiene grupos, proceder a inactivar
                    $this->record->update([
                        'estado_asesor' => 'inactivo'
                    ]);
                    // Desactivar el usuario asociado si existe
                    if ($this->record->user) {
                        $this->record->user->update([
                            'active' => false
                        ]);
                    }
                    $nombreAsesor = $this->record->persona ? $this->record->persona->nombre . ' ' . $this->record->persona->apellidos : '';
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Asesor eliminado')
                        ->body('El asesor ' . $nombreAsesor . ' ha sido eliminado correctamente.')
                        ->send();
                    return redirect()->to(static::getResource()::getUrl('index'));
                })
                ->requiresConfirmation()
                ->modalDescription('¿Está seguro de que desea desactivar este asesor? El asesor quedará inactivo pero su información se mantendrá en el sistema.')
                ->modalHeading('Desactivar Asesor'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}


