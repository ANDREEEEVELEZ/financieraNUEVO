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
            'cuota_id' => CuotasGrupales::factory(), // crea una cuota automáticamente
            'monto' => $this->faker->randomFloat(2, 50, 500),
            'fecha_pago' => now(),
        ];
    }
}
