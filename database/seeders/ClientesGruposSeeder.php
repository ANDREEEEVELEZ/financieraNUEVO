<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\Grupo;
use App\Models\Persona;
use Illuminate\Database\Seeder;

/**
 * Crea un grupo de desarrollo con clientes asociados al asesor de prueba.
 *
 * Debe ejecutarse DESPUÉS de UsersSeeder (necesita asesor@test.com).
 */
class ClientesGruposSeeder extends Seeder
{
    public function run(): void
    {
        $asesor = Asesor::whereHas('user', fn ($q) => $q->where('email', 'asesor@test.com'))->first();

        if (! $asesor) {
            throw new \RuntimeException('Ejecutá UsersSeeder primero — asesor@test.com no existe.');
        }

        // ─── Grupo 1: "Emprendedores Unidos" ───────────────────────────
        $grupo1 = Grupo::create([
            'nombre_grupo'       => 'Emprendedores Unidos',
            'asesor_id'          => $asesor->id,
            'fecha_registro'     => now()->toDateString(),
            'numero_integrantes' => 4,
            'calificacion_grupo' => 'A',
            'estado_grupo'       => 'activo',
        ]);

        $clientesGrupo1 = $this->crearClientes($grupo1->id, [
            ['nombre' => 'Carlos', 'apellidos' => 'Lopez',    'DNI' => '11111111', 'celular' => '911111111'],
            ['nombre' => 'Maria',  'apellidos' => 'Garcia',   'DNI' => '22222222', 'celular' => '922222222'],
            ['nombre' => 'Jose',   'apellidos' => 'Martinez', 'DNI' => '33333333', 'celular' => '933333333'],
            ['nombre' => 'Ana',    'apellidos' => 'Rodriguez', 'DNI' => '44444444', 'celular' => '944444444'],
        ]);

        // ─── Grupo 2: "Futuro Prospero" (opcional, 3 integrantes) ─────
        $grupo2 = Grupo::create([
            'nombre_grupo'       => 'Futuro Prospero',
            'asesor_id'          => $asesor->id,
            'fecha_registro'     => now()->toDateString(),
            'numero_integrantes' => 3,
            'calificacion_grupo' => 'A',
            'estado_grupo'       => 'activo',
        ]);

        $this->crearClientes($grupo2->id, [
            ['nombre' => 'Luis',   'apellidos' => 'Fernandez', 'DNI' => '55555555', 'celular' => '955555555'],
            ['nombre' => 'Sofia',  'apellidos' => 'Torres',    'DNI' => '66666666', 'celular' => '966666666'],
            ['nombre' => 'Pedro',  'apellidos' => 'Diaz',      'DNI' => '77777777', 'celular' => '977777777'],
        ]);
    }

    /**
     * @param array<int, array{nombre: string, apellidos: string, DNI: string, celular: string}> $data
     * @return list<Cliente>
     */
    private function crearClientes(int $grupoId, array $data): array
    {
        $clientes = [];

        foreach ($data as $item) {
            $persona = Persona::create([
                'nombre'    => $item['nombre'],
                'apellidos' => $item['apellidos'],
                'DNI'       => $item['DNI'],
                'celular'   => $item['celular'],
                'correo'    => strtolower($item['nombre']) . '.' . strtolower($item['apellidos']) . '@test.com',
            ]);

            $cliente = Cliente::create([
                'persona_id'     => $persona->id,
                'estado_cliente' => 'activo',
            ]);

            $cliente->grupos()->attach($grupoId, [
                'fecha_ingreso'    => now(),
                'estado_grupo_cliente' => 'activo',
            ]);

            $clientes[] = $cliente;
        }

        return $clientes;
    }
}
