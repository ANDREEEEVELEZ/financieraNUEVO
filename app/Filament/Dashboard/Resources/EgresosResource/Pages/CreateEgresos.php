<?php

namespace App\Filament\Dashboard\Resources\EgresosResource\Pages;

use App\Filament\Dashboard\Resources\EgresosResource;
use App\Models\Categoria;
use App\Models\Subcategoria;
use App\Models\Prestamo;
use Filament\Resources\Pages\CreateRecord;

class CreateEgresos extends CreateRecord
{
    protected static string $resource = EgresosResource::class;

    /**
     * Mutar los datos del formulario antes de crear el registro
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Para gastos
        if ($data['tipo_egreso'] === 'gasto') {
            if (empty(trim($data['descripcion'] ?? ''))) {
                if (!empty($data['categoria_id']) && !empty($data['subcategoria_id'])) {
                    $categoria = Categoria::find($data['categoria_id']);
                    $subcategoria = Subcategoria::find($data['subcategoria_id']);
                    
                    if ($categoria && $subcategoria) {
                        $data['descripcion'] = $categoria->nombre_categoria . ' de ' . $subcategoria->nombre_subcategoria;
                    }
                }
            }
        }
        
        // Para desembolsos
        if ($data['tipo_egreso'] === 'desembolso') {
            if (empty(trim($data['descripcion'] ?? ''))) {
                if (!empty($data['prestamo_id'])) {
                    $prestamo = Prestamo::with('grupo')->find($data['prestamo_id']);
                    if ($prestamo) {
                        $grupoNombre = $prestamo->grupo->nombre_grupo ?? 'Sin grupo';
                        $data['descripcion'] = "Desembolso {$grupoNombre}";
                    }
                }
            }
        }

        // Asegurar que descripcion nunca esté vacía
        if (empty(trim($data['descripcion'] ?? ''))) {
            $data['descripcion'] = 'Sin descripción';
        }

        return $data;
    }
}