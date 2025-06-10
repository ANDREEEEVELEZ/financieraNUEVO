<?php

namespace App\Filament\Dashboard\Resources\EgresosResource\Pages;

use App\Filament\Dashboard\Resources\EgresosResource;
use App\Models\Categoria;
use App\Models\Subcategoria;
use App\Models\Prestamo;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEgresos extends EditRecord
{
    protected static string $resource = EgresosResource::class;

    protected function getHeaderActions(): array
    {
        return [
           // Actions\DeleteAction::make(),
        ];
    }
            protected function isFormDisabled(): bool
    {
        return true;
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * Mutar los datos del formulario antes de guardar el registro
     */
    protected function mutateFormDataBeforeSave(array $data): array
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

        // Asegurar que monto nunca esté vacío
        if (empty($data['monto']) || $data['monto'] <= 0) {
            $data['monto'] = 0.00;
        }

        return $data;
    }
}
