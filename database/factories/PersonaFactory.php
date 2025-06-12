<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Persona>
 */
class PersonaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'DNI' => $this->faker->unique()->numerify('########'),
            'nombre' => $this->faker->firstName,
            'apellidos' => $this->faker->lastName,
            'sexo' => $this->faker->randomElement(['Masculino', 'Femenino']),
            'fecha_nacimiento' => $this->faker->date('Y-m-d'),
            'celular' => $this->faker->phoneNumber,
            'correo' => $this->faker->unique()->safeEmail,
            'direccion' => $this->faker->address,
            'distrito' => $this->faker->city,
            'estado_civil' => $this->faker->randomElement(['soltero', 'casado', 'divorciado']),

        ];
    }
}
