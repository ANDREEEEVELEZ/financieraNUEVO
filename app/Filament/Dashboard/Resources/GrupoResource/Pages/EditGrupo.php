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
    
    protected bool $skipAfterSave = false;


    protected function getHeaderActions(): array
    {
        // Solo mostrar la acción de inactivar si el grupo está activo
        if ($this->record->estado_grupo === 'Activo') {
            return [
                Actions\Action::make('inactivar')
                    ->label('Inactivar Grupo')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('¿Inactivar Grupo?')
                    ->modalDescription('Esta acción inactivará el grupo y todos sus integrantes. ¿Estás seguro?')
                    ->action(function () {
                        // Cambiar el estado del grupo
                        $this->record->estado_grupo = 'Inactivo';
                        $this->record->save();
                        
                        // Sincronizar el estado de todos los integrantes activos
                        // SOLO cambiar estado, NO agregar fecha_salida (no son ex-integrantes)
                        $integrantesActivos = $this->record->clientes()
                            ->wherePivot('estado_grupo_cliente', 'Activo')
                            ->pluck('clientes.id')
                            ->toArray();
                        
                        if (!empty($integrantesActivos)) {
                            $this->record->clientes()->updateExistingPivot(
                                $integrantesActivos,
                                [
                                    'estado_grupo_cliente' => 'Inactivo',
                                    'updated_at' => now()
                                ]
                            );
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Grupo inactivado')
                            ->body('El grupo y todos sus integrantes han sido inactivados exitosamente.')
                            ->send();
                        
                        $this->redirect($this->getRedirectUrl());
                    }),
            ];
        }
        
        // Los grupos inactivos no tienen acciones disponibles
        return [];
    }


    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Procesar remoción de integrantes
        if (!empty($data['remover_integrantes_form']) && !$this->record->tienePrestamosActivos()) {
            $this->skipAfterSave = true;
            try {
                $clientesRemovidosNombres = [];
                foreach ($data['remover_integrantes_form'] as $clienteId) {
                    $cliente = \App\Models\Cliente::with('persona')->find($clienteId);
                    if ($cliente) {
                        $clientesRemovidosNombres[] = $cliente->persona->nombre . ' ' . $cliente->persona->apellidos;
                        $this->record->removerCliente($clienteId);
                    }
                }
                
                if (!empty($clientesRemovidosNombres)) {
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Integrantes removidos exitosamente')
                        ->body('Se removieron: ' . implode(', ', $clientesRemovidosNombres))
                        ->send();
                }
                    
            } catch (\Exception $e) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('Error al remover integrantes')
                    ->body($e->getMessage())
                    ->send();
            }
            
            // Limpiar el campo después de procesar
            unset($data['remover_integrantes_form']);
        }

        // Procesar transferencia de integrante
        if (!empty($data['cliente_transferir']) && !empty($data['grupo_destino_form']) && !$this->record->tienePrestamosActivos()) {
            $this->skipAfterSave = true;
            try {
                $cliente = \App\Models\Cliente::with('persona')->find($data['cliente_transferir']);
                $grupoDestino = \App\Models\Grupo::find($data['grupo_destino_form']);
                
                if ($cliente && $grupoDestino) {
                    // Ejecutar la transferencia
                    $this->record->transferirClienteAGrupo(
                        $data['cliente_transferir'], 
                        $data['grupo_destino_form'],
                        now()->format('Y-m-d')
                    );
                    
                    \Filament\Notifications\Notification::make()
                        ->success()
                        ->title('Integrante transferido exitosamente')
                        ->body("El cliente {$cliente->persona->nombre} {$cliente->persona->apellidos} ha sido transferido al grupo {$grupoDestino->nombre_grupo}.")
                        ->send();
                        
                    // Recargar la página para reflejar los cambios
                    $this->redirect($this->getRedirectUrl());
                    return $data;
                }
                    
            } catch (\Exception $e) {
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('Error al transferir integrante')
                    ->body($e->getMessage())
                    ->send();
            }
            
            // Limpiar los campos después de procesar
            unset($data['cliente_transferir'], $data['grupo_destino_form']);
        }

        // Si hay clientes seleccionados, actualiza el número de integrantes
        if (isset($data['clientes'])) {
            $data['numero_integrantes'] = is_array($data['clientes']) ? count($data['clientes']) : 0;
        }
        
        // Estado por defecto
        $data['estado_grupo'] = $data['estado_grupo'] ?? 'Activo';
        
        // Validar que el líder esté entre los clientes seleccionados
        if (!empty($data['clientes']) && !empty($data['lider_grupal']) && !in_array($data['lider_grupal'], $data['clientes'])) {
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
        // Si se procesaron transferencias o remociones, no ejecutar la sincronización automática
        if ($this->skipAfterSave) {
            return;
        }
        
        $clientes = $this->data['clientes'] ?? [];
        $liderId = $this->data['lider_grupal'] ?? null;
        $fechaHoy = now()->toDateString();
        $syncData = [];
        
        // Si no se especifica líder, mantener el líder actual si existe
        if (!$liderId) {
            $liderActual = $this->record->clientes()->wherePivot('rol', 'Líder Grupal')->first();
            if ($liderActual && in_array($liderActual->id, $clientes)) {
                $liderId = $liderActual->id;
            }
        }
        
        // Obtener todos los integrantes existentes (activos e inactivos)
        $existingClientes = $this->record->todosLosIntegrantes()
            ->withPivot(['fecha_ingreso', 'estado_grupo_cliente', 'rol', 'fecha_salida'])
            ->get();
        
        // Procesar clientes existentes
        foreach ($existingClientes as $cliente) {
            if (in_array($cliente->id, $clientes)) {
                // Cliente sigue en el grupo - mantener como activo
                $syncData[$cliente->id] = [
                    'rol' => ($cliente->id == $liderId) ? 'Líder Grupal' : 'Miembro',
                    'fecha_ingreso' => $cliente->pivot->fecha_ingreso ?? $fechaHoy,
                    'estado_grupo_cliente' => 'Activo',
                    'fecha_salida' => null,
                    'updated_at' => now()
                ];
            } else {
                // Cliente ya no está en la selección - marcar como inactivo (ex-integrante)
                $syncData[$cliente->id] = [
                    'rol' => $cliente->pivot->rol,
                    'fecha_ingreso' => $cliente->pivot->fecha_ingreso ?? $fechaHoy,
                    'estado_grupo_cliente' => 'Inactivo',
                    'fecha_salida' => $cliente->pivot->fecha_salida ?? $fechaHoy,
                    'updated_at' => now()
                ];
            }
        }
        
        // Agregar nuevos clientes que no estaban antes en el grupo
        foreach ($clientes as $clienteId) {
            if (!$existingClientes->contains('id', $clienteId)) {
                $syncData[$clienteId] = [
                    'rol' => ($clienteId == $liderId) ? 'Líder Grupal' : 'Miembro',
                    'fecha_ingreso' => $fechaHoy,
                    'estado_grupo_cliente' => 'Activo',
                    'fecha_salida' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }
        
        // Si el grupo está inactivo, todos los integrantes deben estar inactivos
        if ($this->record->estado_grupo === 'Inactivo') {
            foreach ($syncData as &$pivotData) {
                $pivotData['estado_grupo_cliente'] = 'Inactivo';
                if ($pivotData['fecha_salida'] === null) {
                    $pivotData['fecha_salida'] = $fechaHoy;
                }
                $pivotData['updated_at'] = now();
            }
            unset($pivotData);
        }
        
        // Sincronizar con la tabla pivot
        $this->record->todosLosIntegrantes()->sync($syncData);
        
        // Actualizar el número de integrantes activos
        $numActivos = $this->record->clientes()->count();
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
        
        // Obtener los IDs de los clientes activos del grupo
        $data['clientes'] = $this->record->clientes()->pluck('clientes.id')->toArray();
        
        // Obtener el líder grupal
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


