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
            if ($asesor) {
                $data['asesor_id'] = $asesor->id;
            }
        }
        return $data;
    }

    protected function afterCreate(): void
    {
        $clientes = $this->data['clientes'] ?? [];
        $liderId = $this->data['lider_grupal'] ?? null;
        $fechaHoy = now()->toDateString();
        $estadoGrupo = $this->record->estado_grupo;
        if (!empty($clientes)) {
            $syncData = [];
            foreach ($clientes as $clienteId) {
                $syncData[$clienteId] = [
                    'rol' => ($clienteId == $liderId) ? 'Líder Grupal' : 'Miembro',
                    'fecha_ingreso' => $fechaHoy,
                    'estado_grupo_cliente' => $estadoGrupo,
                ];
            }
            $this->record->clientes()->sync($syncData);
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
