<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Retanqueo;
use App\Models\Prestamo;
use App\Models\Grupo;
use App\Models\Asesor;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Retanqueo>
 */
class RetanqueoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prestamo_id' => Prestamo::factory(),
            'grupo_id' => Grupo::factory(),
            'asesore_id' => Asesor::factory(),
            'monto_retanqueado' => $this->faker->randomFloat(2, 500, 3000),
            'monto_devolver' => $this->faker->randomFloat(2, 600, 3500),
            'monto_desembolsar' => $this->faker->randomFloat(2, 500, 3000),
            'cantidad_cuotas_retanqueo' => $this->faker->numberBetween(3, 10),
            'aceptado' => $this->faker->boolean(),
            'fecha_aceptacion' => $this->faker->date('Y-m-d'),
            'estado_retanqueo' => $this->faker->randomElement(['pendiente', 'aprobado', 'rechazado']),
        ];
    }
}
