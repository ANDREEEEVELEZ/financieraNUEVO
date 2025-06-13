<?php

namespace Database\Factories;

use App\Models\CuotasGrupales;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Prestamo;

class CuotasGrupalesFactory extends Factory
{
    protected $model = CuotasGrupales::class;

    public function definition(): array
    {
        return [
            'prestamo_id' => Prestamo::factory()->aprobado(),
            'numero_cuota' => $this->faker->numberBetween(1, 12),
            'monto_cuota_grupal' => $this->faker->randomFloat(2, 100, 500),
            'fecha_vencimiento' => now()->toDateString(),
            'saldo_pendiente' => $this->faker->randomFloat(2, 50, 500),
            'estado_cuota_grupal' => $this->faker->randomElement(['vigente', 'mora', 'cancelada']),
            'estado_pago' => $this->faker->randomElement(['pendiente', 'pagado', 'parcial']),
        ];
    }
}
