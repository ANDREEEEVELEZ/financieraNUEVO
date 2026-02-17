<?php

namespace Database\Seeders;

use App\Models\ProductoFinanciero;
use Illuminate\Database\Seeder;

class ProductoFinancieroSeeder extends Seeder
{
    /**
     * Crea los productos financieros base del sistema.
     */
    public function run(): void
    {
        $productos = [
            [
                'codigo' => 'CG-BÁSICO',
                'nombre' => 'Crédito Grupal Básico',
                'tipo' => 'grupal',
                'tasa_interes' => 0.10,
                'tasa_mora' => 0.05,
                'permite_condonacion_mora' => true,
                'monto_minimo' => 1000.00,
                'monto_maximo' => 20000.00,
                'plazo_minimo_meses' => 3,
                'plazo_maximo_meses' => 12,
                'activo' => true,
                'config_json' => [
                    'calculo_interes' => 'flat',
                    'permite_prepago' => true,
                    'penalidad_prepago' => 0,
                ],
            ],
            [
                'codigo' => 'CG-PREMIUM',
                'nombre' => 'Crédito Grupal Premium',
                'tipo' => 'grupal',
                'tasa_interes' => 0.08,
                'tasa_mora' => 0.04,
                'permite_condonacion_mora' => true,
                'monto_minimo' => 5000.00,
                'monto_maximo' => 50000.00,
                'plazo_minimo_meses' => 6,
                'plazo_maximo_meses' => 24,
                'activo' => true,
                'config_json' => [
                    'calculo_interes' => 'flat',
                    'permite_prepago' => true,
                    'requiere_ciclo_minimo' => 2,
                ],
            ],
            [
                'codigo' => 'CI-EMPRENDEDOR',
                'nombre' => 'Crédito Individual Emprendedor',
                'tipo' => 'individual',
                'tasa_interes' => 0.12,
                'tasa_mora' => 0.06,
                'permite_condonacion_mora' => false,
                'monto_minimo' => 500.00,
                'monto_maximo' => 10000.00,
                'plazo_minimo_meses' => 1,
                'plazo_maximo_meses' => 12,
                'activo' => true,
                'config_json' => [
                    'calculo_interes' => 'flat',
                    'requiere_garantia' => false,
                ],
            ],
        ];

        foreach ($productos as $producto) {
            ProductoFinanciero::updateOrCreate(
                ['codigo' => $producto['codigo']],
                $producto
            );
        }

        $this->command->info('✓ Productos financieros creados: ' . count($productos));
    }
}
