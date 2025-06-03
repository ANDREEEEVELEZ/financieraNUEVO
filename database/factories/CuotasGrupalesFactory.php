<?php

namespace Database\Factories;
use App\Models\CuotasGrupales;
use Illuminate\Database\Eloquent\Factories\Factory;

class CuotasGrupalesFactory extends Factory
{
    protected $model = CuotasGrupales::class;

    public function definition(): array
    {
        return [
            'monto' => $this->faker->randomFloat(2, 100, 1000),
            'fecha_vencimiento' => now()->addDays(7),
            'grupo_id' => \App\Models\Grupo::factory(), // si aplica
        ];
    }
}