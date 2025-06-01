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
                ->action(function () {
                    // En lugar de eliminar, cambiar el estado a inactivo
                    $this->record->update([
                        'estado_asesor' => 'inactivo'
                    ]);

                    // Desactivar el usuario asociado si existe
                    if ($this->record->user) {
                        $this->record->user->update([
                            'active' => false
                        ]);
                    }

                    Notification::make()
                        ->success()
                        ->title('Asesor desactivado')
                        ->body('El asesor ha sido desactivado correctamente.')
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


