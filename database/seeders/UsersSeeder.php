<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Asesor;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea los 4 usuarios del sistema con sus roles asignados.
 *
 * NO crea permisos ni roles — eso es responsabilidad de PermissionSeeder.
 * Debe ejecutarse DESPUÉS de PermissionSeeder.
 */
class UsersSeeder extends Seeder
{
    private const string PASSWORD = 'test';

    /**
     * @var array<array{name: string, email: string, role: string}>
     */
    private const array USERS = [
        ['name' => 'Super Admin',    'email' => 'sa@test.com',      'role' => 'super_admin'],
        ['name' => 'Jefe Operaciones', 'email' => 'jo@test.com',   'role' => 'Jefe de operaciones'],
        ['name' => 'Jefe Creditos',  'email' => 'jc@test.com',     'role' => 'Jefe de creditos'],
        ['name' => 'Asesor Test',    'email' => 'asesor@test.com', 'role' => 'Asesor'],
    ];

    public function run(): void
    {
        $password = Hash::make(self::PASSWORD);

        foreach (self::USERS as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);
            $user->name = $data['name'];
            $user->password ??= $password;
            $user->save();

            // Asignar rol limpio (detach previo para idempotencia)
            $user->syncRoles([$data['role']]);

            if ($data['role'] === 'Asesor') {
                $this->crearPerfilAsesor($user);
            }
        }
    }

    private function crearPerfilAsesor(User $user): void
    {
        $persona = Persona::updateOrCreate(
            ['correo' => $user->email],
            [
                'nombre'    => 'Asesor',
                'apellidos' => 'Test',
                'DNI'       => '12345678',
                'celular'   => '999888777',
                'direccion' => 'Calle Falsa 123',
            ],
        );

        Asesor::updateOrCreate(
            ['user_id' => $user->id],
            [
                'persona_id'    => $persona->id,
                'codigo_asesor' => 'ASE-TEST-001',
                'estado_asesor' => 'activo',
                'fecha_ingreso' => now(),
            ],
        );
    }
}
