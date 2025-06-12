<?php

namespace Database\Factories;
use App\Models\CuotasGrupales;
use App\Models\Mora;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mora>
 */
class MoraFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
             'cuota_grupal_id' => CuotasGrupales::factory(),
            'estado_mora' => $this->faker->randomElement(['pendiente', 'pagada','parcialmente_pagada']),
            'fecha_atraso' => $this->faker->date('Y-m-d'),
        ];
    }
}
