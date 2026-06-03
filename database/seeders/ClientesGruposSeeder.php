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

        // ─── Nuevos 40 clientes de prueba ──────────────────────────────
        $nuevosClientes = [];
        for ($i = 1; $i <= 40; $i++) {
            $persona = Persona::create([
                'nombre'    => 'TestNombre' . $i,
                'apellidos' => 'TestApellido' . $i,
                'DNI'       => str_pad((string)(80000000 + $i), 8, '0', STR_PAD_LEFT),
                'celular'   => str_pad((string)(900000000 + $i), 9, '0', STR_PAD_LEFT),
                'correo'    => "testcliente{$i}@test.com",
            ]);

            $cliente = Cliente::create([
                'persona_id'     => $persona->id,
                'estado_cliente' => 'activo',
                'asesor_id'      => $asesor->id,
            ]);

            $nuevosClientes[] = $cliente;
        }

        $clienteIndex = 0;

        // ─── 4 grupos de 4 personas (16 clientes) ──────────────────────
        for ($g = 1; $g <= 4; $g++) {
            $grupo = Grupo::create([
                'nombre_grupo'       => "Grupo Test Cuatro $g",
                'asesor_id'          => $asesor->id,
                'fecha_registro'     => now()->toDateString(),
                'numero_integrantes' => 4,
                'calificacion_grupo' => 'A',
                'estado_grupo'       => 'activo',
            ]);

            for ($i = 0; $i < 4; $i++) {
                $nuevosClientes[$clienteIndex]->grupos()->attach($grupo->id, [
                    'fecha_ingreso'    => now(),
                    'estado_grupo_cliente' => 'activo',
                ]);
                $clienteIndex++;
            }
        }

        // ─── 2 grupos de 5 personas (10 clientes) ──────────────────────
        for ($g = 1; $g <= 2; $g++) {
            $grupo = Grupo::create([
                'nombre_grupo'       => "Grupo Test Cinco $g",
                'asesor_id'          => $asesor->id,
                'fecha_registro'     => now()->toDateString(),
                'numero_integrantes' => 5,
                'calificacion_grupo' => 'A',
                'estado_grupo'       => 'activo',
            ]);

            for ($i = 0; $i < 5; $i++) {
                $nuevosClientes[$clienteIndex]->grupos()->attach($grupo->id, [
                    'fecha_ingreso'    => now(),
                    'estado_grupo_cliente' => 'activo',
                ]);
                $clienteIndex++;
            }
        }
        // Los 14 clientes restantes quedan sin grupo asignado.
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
