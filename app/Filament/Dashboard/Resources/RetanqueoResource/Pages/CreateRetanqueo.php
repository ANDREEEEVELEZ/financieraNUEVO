<?php

namespace App\Filament\Dashboard\Resources\RetanqueoResource\Pages;

use App\Filament\Dashboard\Resources\RetanqueoResource;
use App\Services\RetanqueoService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class CreateRetanqueo extends CreateRecord
{
    protected static string $resource = RetanqueoResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Limpiar datos innecesarios antes de crear
        unset($data['estado_prestamo_info']);
        
        // Asegurar que el estado sea correcto
        $data['estado_retanqueo'] = 'solicitud_pendiente';
        
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            $retanqueoService = new RetanqueoService();
            
            // Extraer participantes del array de datos
            $participantes = $data['participantes'] ?? [];
            $prestamoId = $data['prestamo_id'];
            
            // Datos adicionales del retanqueo
            $datosRetanqueo = [
                'cantidad_cuotas' => $data['cantidad_cuotas_nuevo'] ?? 20,
                'monto_cuota' => null // Se calculará automáticamente
            ];

            // Crear la solicitud usando el servicio
            $retanqueo = $retanqueoService->crearSolicitudRetanqueo(
                $prestamoId,
                $participantes,
                $datosRetanqueo
            );

            Log::info('Solicitud de retanqueo creada exitosamente', [
                'retanqueo_id' => $retanqueo->id,
                'prestamo_id' => $prestamoId,
                'user_id' => request()->user()?->id
            ]);

            return $retanqueo;

        } catch (\Exception $e) {
            Log::error('Error al crear solicitud de retanqueo', [
                'error' => $e->getMessage(),
                'prestamo_id' => $data['prestamo_id'] ?? null,
                'user_id' => request()->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Solicitud Creada')
            ->body('La solicitud de retanqueo ha sido creada exitosamente y está pendiente de aprobación.')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Crear Solicitud de Retanqueo'),
            $this->getCancelFormAction(),
        ];
    }

    protected function getCreateFormAction(): Actions\Action
    {
        return Actions\Action::make('create')
            ->label('Crear Solicitud de Retanqueo')
            ->submit('create')
            ->keyBindings(['mod+s'])
            ->color('primary')
            ->icon('heroicon-m-plus')
            ->requiresConfirmation()
            ->modalHeading('Confirmar Creación de Solicitud')
            ->modalDescription('¿Está seguro de que desea crear esta solicitud de retanqueo con la configuración especificada?')
            ->modalSubmitActionLabel('Sí, crear solicitud');
    }
}
