<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ProductoFinanciero;

class ProductoFinancieroFactory extends Factory
{
    protected $model = ProductoFinanciero::class;

    public function definition(): array
    {
        $tipo = fake()->randomElement(['grupal', 'individual']);
        return [
            'codigo'                  => strtoupper($this->faker->unique()->lexify('PROD-???')),
            'nombre'                  => $this->faker->words(3, true),
            'tipo'                    => $tipo,
            'tasa_interes'            => $this->faker->randomFloat(4, 0.10, 0.25),
            'tasa_mora'               => $this->faker->randomFloat(4, 0.01, 0.05),
            'permite_condonacion_mora'=> false,
            'permite_retanqueo'       => false,
            'monto_minimo'            => 500.00,
            'monto_maximo'            => 10000.00,
            'plazo_minimo_meses'      => 1,
            'plazo_maximo_meses'      => 12,
            'config_json'             => null,
            'activo'                  => true,
        ];
    }

    public function conRetanqueo(): static
    {
        return $this->state(['permite_retanqueo' => true]);
    }
}
