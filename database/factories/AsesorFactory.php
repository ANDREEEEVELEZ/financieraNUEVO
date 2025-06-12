<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Persona;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asesor>
 */
class AsesorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [

            'persona_id' => Persona::factory(),
            'user_id' => User::factory(),
            'codigo_asesor' => $this->faker->unique()->bothify('ASR-####'),
            'fecha_ingreso' => $this->faker->date('Y-m-d'),
            'estado_asesor' => $this->faker->randomElement(['activo', 'inactivo']),


        ];
    }
}
