<?php

namespace App\Filament\Dashboard\Resources\GrupoResource\Pages;

use App\Filament\Dashboard\Resources\GrupoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use App\Models\Cliente;

class EditGrupo extends EditRecord
{
    protected static string $resource = GrupoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
        return $data;
    }

    protected function afterSave(): void
    {
        $clientes = $this->data['clientes'] ?? [];
        $liderId = $this->data['lider_grupal'] ?? null;
        if (!empty($clientes)) {
            $syncData = [];
            foreach ($clientes as $clienteId) {
                $syncData[$clienteId] = [
                    'rol' => ($clienteId == $liderId) ? 'Líder Grupal' : 'Miembro',
                ];
            }
            $this->record->clientes()->sync($syncData);
        }
        // Actualizar el número de integrantes en la tabla grupos
        $this->record->numero_integrantes = count($clientes);
        $this->record->save();
    }

    protected function fillForm(): void
    {
        // Llenar el formulario con todos los datos del grupo
        $data = $this->record->toArray();
        $data['clientes'] = $this->record->clientes()->pluck('clientes.id')->toArray();
        $lider = $this->record->clientes()->wherePivot('rol', 'Líder Grupal')->first();
        if ($lider) {
            $data['lider_grupal'] = $lider->id;
        }
        $this->form->fill($data);
    }
    
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
