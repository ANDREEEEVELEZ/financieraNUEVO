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
            'nombre_grupo' => 'Grupo ' . rand(1, 1000),
            'numero_integrantes' => $this->faker->numberBetween(3, 10),
            'fecha_registro'=> now()->toDateString(),
            'calificacion_grupo' => $this->faker->randomFloat(2, 0, 10),
            'estado_grupo'=> fake()->randomElement(['activo', 'inactivo']),
            'asesor_id' => Asesor::factory(),
        ];
    }
}
