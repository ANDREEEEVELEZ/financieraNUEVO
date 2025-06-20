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
        // Solo mostrar el botón si el grupo está ACTIVO
        if ($this->record->estado_grupo !== 'Activo') {
            return [];
        }
        return [
            Actions\Action::make('inactivar')
                ->label('Inactivar Grupo')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->estado_grupo = 'Inactivo';
                    $this->record->save();
                    $this->redirect($this->getRedirectUrl());
                }),
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
        $user = request()->user();
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $data['asesor_id'] = $asesor->id;
            }
        }
        return $data;
    }


    protected function afterSave(): void
    {
        $clientes = $this->data['clientes'] ?? [];
        $liderId = $this->data['lider_grupal'] ?? null;
        $fechaHoy = now()->toDateString();
        $estadoGrupo = $this->record->estado_grupo;
        $syncData = [];
        // Obtener todos los clientes actuales (activos e inactivos) en el grupo
        $clientesPivot = $this->record->clientes()->withPivot(['fecha_ingreso', 'estado_grupo_cliente', 'rol'])->get();
        $clientesPivotIds = $clientesPivot->pluck('id')->toArray();
        // Marcar como inactivo a los que ya no están en la selección
        foreach ($clientesPivot as $cliente) {
            if (!in_array($cliente->id, $clientes)) {
                $syncData[$cliente->id] = [
                    'rol' => $cliente->pivot->rol,
                    'fecha_ingreso' => $cliente->pivot->fecha_ingreso,
                    'estado_grupo_cliente' => 'Inactivo',
                ];
            }
        }
        // Actualizar o agregar los clientes seleccionados
        foreach ($clientes as $clienteId) {
            $pivot = $this->record->clientes()->wherePivot('cliente_id', $clienteId)->first();
            $syncData[$clienteId] = [
                'rol' => ($clienteId == $liderId) ? 'Líder Grupal' : 'Miembro',
                'fecha_ingreso' => $pivot ? $pivot->pivot->fecha_ingreso : $fechaHoy,
                'estado_grupo_cliente' => 'Activo',
            ];
        }
        // Si el grupo está inactivo, todos los integrantes deben estar inactivos
        if ($this->record->estado_grupo === 'Inactivo') {
            foreach ($syncData as &$pivotData) {
                $pivotData['estado_grupo_cliente'] = 'Inactivo';
            }
            unset($pivotData);
        }
        $this->record->clientes()->sync($syncData);
        // Actualizar el número de integrantes activos en la tabla grupos
        $numActivos = $this->record->clientes()->wherePivot('estado_grupo_cliente', 'Activo')->count();
        $this->record->numero_integrantes = $numActivos;
        // Si no hay integrantes activos, poner el grupo como inactivo
        if ($numActivos === 0 && $this->record->estado_grupo !== 'Inactivo') {
            $this->record->estado_grupo = 'Inactivo';
        }
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
   
    public function retirarClienteDelGrupo($clienteId)
    {
        $grupo = $this->record;
        $cliente = Cliente::findOrFail($clienteId);
        $action = new \App\Filament\Dashboard\Resources\GrupoResource\Actions\RetirarClienteAction();
        return $action::retirar($grupo, $cliente);
    }
   
    protected function getHistorialIntegrantes()
    {
        // Devuelve todos los integrantes (activos e inactivos) con su estado y fechas
        return $this->record->clientes()->withPivot(['fecha_ingreso', 'estado_grupo_cliente', 'rol'])->get()->map(function($cliente) {
            return [
                'nombre' => $cliente->persona->nombre . ' ' . $cliente->persona->apellidos,
                'dni' => $cliente->persona->DNI,
                'rol' => $cliente->pivot->rol,
                'fecha_ingreso' => $cliente->pivot->fecha_ingreso,
                'estado' => $cliente->pivot->estado_grupo_cliente,
            ];
        });
    }
}


