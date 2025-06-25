<?php

namespace App\Filament\Dashboard\Resources\GrupoResource\Pages;

use App\Filament\Dashboard\Resources\GrupoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use App\Models\Cliente;

class CreateGrupo extends CreateRecord
{
    protected static string $resource = GrupoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Si hay clientes seleccionados, actualiza el número de integrantes
        if (isset($data['clientes'])) {
            $data['numero_integrantes'] = is_array($data['clientes']) ? count($data['clientes']) : 0;
        }
        // Estado por defecto
        $data['estado_grupo'] = $data['estado_grupo'] ?? 'Activo';
        // Validar que el líder esté entre los clientes seleccionados
        if (!empty($data['clientes']) && !in_array($data['lider_grupal'], $data['clientes'])) {
            throw new \Exception('El líder grupal debe ser uno de los integrantes seleccionados.');
        }
        
        $user = request()->user();

        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();

            if (!$asesor) {
                throw new \Illuminate\Validation\ValidationException(
                    \Illuminate\Support\Facades\Validator::make([], [
                        'asesor_id' => 'El usuario autenticado no tiene un asesor asociado.',
                    ])
                );
            }

            $data['asesor_id'] = $asesor->id; // Asignar el ID del asesor desde la tabla `asesores`
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $clientes = $this->data['clientes'] ?? [];
        $liderId = $this->data['lider_grupal'] ?? null;
        if (!empty($clientes)) {
            $syncData = [];
            $fechaHoy = now()->toDateString();
            foreach ($clientes as $clienteId) {
                $syncData[$clienteId] = [
                    'rol' => ($clienteId == $liderId) ? 'Líder Grupal' : 'Miembro',
                    'fecha_ingreso' => $fechaHoy,
                    'estado_grupo_cliente' => 'Activo',
                    'fecha_salida' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            $this->record->todosLosIntegrantes()->sync($syncData);
        }
        // Actualizar el número de integrantes en la tabla grupos
        $this->record->numero_integrantes = count($clientes);
        $this->record->save();
    }
    
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
