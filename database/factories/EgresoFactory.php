<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Prestamo;
use App\Models\Categoria;
use App\Models\Subcategoria;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Egreso>
 */
class EgresoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_egreso' => $this->faker->randomElement(['gasto', 'desembolso']),
            'fecha' => now()->toDateString(),
            'descripcion' => $this->faker->sentence(),
            'monto' => $this->faker->randomFloat(2, 100, 2000),
            'prestamo_id' => Prestamo::factory(),
            'categoria_id' => Categoria::factory(),
            'subcategoria_id' => Subcategoria::factory(),
        ];
    }
}
