<?php

namespace Database\Factories;
use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\Persona;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cliente>
 */
class ClienteFactory extends Factory
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
            'infocorp' => $this->faker->boolean(),
            'ciclo' => $this->faker->numberBetween(1, 5),
            'condicion_vivienda' => $this->faker->randomElement(['propia', 'alquilada', 'familiar']),
            'actividad' => $this->faker->word(),
            'condicion_personal' => $this->faker->randomElement(['activo', 'moroso']),
            'estado_cliente' => $this->faker->randomElement(['activo', 'inactivo']),
            'asesor_id' => Asesor::factory(),

        ];
    }
}
