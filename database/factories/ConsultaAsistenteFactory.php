<?php

namespace Database\Factories;

use App\Models\ConsultaAsistente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsultaAsistenteFactory extends Factory
{
    protected $model = ConsultaAsistente::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'consulta' => $this->faker->sentence(),
            'respuesta' => $this->faker->paragraph(), // Siempre con valor
        ];
    }

    // Estado para crear con respuesta vacía (pero no null)
    public function conRespuestaVacia(): static
    {
        return $this->state(fn (array $attributes) => [
            'respuesta' => '', // String vacío en lugar de null
        ]);
    }
}
