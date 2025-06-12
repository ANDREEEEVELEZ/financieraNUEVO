<?php

namespace Database\Factories;
use App\Models\Grupo;
use App\Models\Asesor;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Grupo>
 */
class GrupoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre_grupo' => $this->faker->company(),
            'numero_integrantes' => $this->faker->numberBetween(3, 10),
            'fecha_registro' => $this->faker->date('Y-m-d'),
            'calificacion_grupo' => $this->faker->randomFloat(2, 0, 10),
            'estado_grupo' => $this->faker->randomElement(['activo', 'inactivo']),
            'asesor_id' => Asesor::factory(),
        ];
    }
}
