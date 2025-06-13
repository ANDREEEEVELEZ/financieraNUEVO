<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Faker\Factory as FakerFactory;

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
    $faker = FakerFactory::create('es_PE'); // o sin localización
    $nombres = ['Juan', 'María', 'Carlos', 'Ana', 'Luis', 'Carmen', 'José', 'Isabel'];
    $apellidos = ['García', 'López', 'Martínez', 'González'];

    return [
        'DNI' => str_pad($faker->unique()->numberBetween(1, 99999999), 8, '0', STR_PAD_LEFT),
        'nombre' => $faker->randomElement($nombres),
        'apellidos' => $faker->randomElement($apellidos) . ' ' . $faker->randomElement($apellidos),
        'sexo' => $faker->randomElement(['Masculino', 'Femenino']),
        'fecha_nacimiento' => $faker->date('Y-m-d'),
        'celular' => '9' . $faker->numberBetween(10000000, 99999999),
        'correo' => 'user' . $faker->unique()->numberBetween(1, 9999) . '@example.com',
        'direccion' => 'Calle ' . $faker->numberBetween(1, 999),
        'distrito' => $faker->randomElement(['Lima', 'Surco']),
        'estado_civil' => $faker->randomElement(['Soltero', 'Casado']),
    ];
}
}
