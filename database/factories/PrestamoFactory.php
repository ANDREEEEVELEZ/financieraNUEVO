<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Prestamo;
use App\Models\Cliente;
use App\Models\Grupo;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prestamo>
 */
class PrestamoFactory extends Factory
{
    protected $model = Prestamo::class;

    public function definition(): array
    {
        return [
            'grupo_id' => Grupo::factory(),
            'tasa_interes' => $this->faker->randomFloat(2, 2, 10),
            'monto_prestado_total' => $this->faker->randomFloat(2, 500, 5000),
            'monto_devolver' => $this->faker->randomFloat(2, 600, 6000),
            'cantidad_cuotas' => $this->faker->numberBetween(4, 12),
            'fecha_prestamo' => now()->toDateString(),
            'frecuencia' => $this->faker->randomElement(['semanal', 'quincenal', 'mensual']),
            'estado' => $this->faker->randomElement(['Pendiente', 'Aprobado', 'Rechazado']),
            'calificacion' => $this->faker->randomFloat(2, 0, 10),
        ];
    }

    public function aprobado(): static
    {
        return $this->state([
            'estado' => 'Aprobado',
        ]);
    }
}
