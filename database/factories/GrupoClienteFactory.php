<?php

namespace Database\Factories;
use App\Models\GrupoCliente;
use App\Models\Grupo;
use App\Models\Cliente;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GrupoCliente>
 */
class GrupoClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
             'grupo_id' => Grupo::factory(),
            'cliente_id' => Cliente::factory(),
            'fecha_ingreso' => now()->toDateString(),
            'rol' => $this->faker->randomElement(['miembro', 'lider']),
            'estado_grupo_cliente' => $this->faker->randomElement(['activo', 'inactivo']),
        ];
    }
}
