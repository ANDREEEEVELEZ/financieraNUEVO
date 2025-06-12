<?php

namespace Database\Factories;

use App\Models\CuotasGrupales;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pago>
 */
class PagoFactory extends Factory
{
    protected $model = \App\Models\Pago::class;

    public function definition(): array
    {
        return [
            'cuota_grupal_id' => CuotasGrupales::factory(),
            'tipo_pago' => $this->faker->randomElement(['parcial', 'completo']),
            'codigo_operacion' => $this->faker->uuid(),
            'monto_pagado' => $this->faker->randomFloat(2, 50, 1000),
            'monto_mora_pagada' => $this->faker->randomFloat(2, 0, 50),
            'fecha_pago' => $this->faker->dateTime()->format('Y-m-d H:i:s'),
            'estado_pago' => $this->faker->randomElement(['Aprobado', 'Pendiente', 'Rechazado']),
            'observaciones' => $this->faker->sentence(),
        ];
    }
}
