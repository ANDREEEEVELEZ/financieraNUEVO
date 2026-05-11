<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Asesor;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class DevelopmentUsersSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Limpiar cache de Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Definir Permisos (Lista completa extraída de PermissionSeeder)
        $permisos = [
            'clientes.crear', 'clientes.ver', 'clientes.editar', 'clientes.trasladar',
            'view_any_cliente', 'create_cliente', 'update_cliente', 'delete_cliente',
            'grupos.crear', 'grupos.ver', 'grupos.editar', 'grupos.trasladar_asesor',
            'view_any_grupo', 'create_grupo', 'update_grupo', 'delete_grupo',
            'prestamos.crear', 'prestamos.ver_todos', 'prestamos.aprobar', 'prestamos.rechazar',
            'prestamos.firmar', 'prestamos.desembolsar', 'prestamos.reducir_monto',
            'prestamos.reformular', 'prestamos.separar_cliente', 'prestamos.reagrupar',
            'pagos.crear', 'pagos.ver_todos', 'pagos.aprobar', 'pagos.anular', 'pagos.revertir',
            'retanqueos.ver_todos', 'ajustes.condonar_mora', 'ajustes.descontar_capital',
            'ajustes.descontar_interes', 'productos.crear', 'productos.editar', 'productos.ver',
            'productos.activar_desactivar', 'reportes.ver_financieros', 'reportes.ver_mora',
            'reportes.exportar', 'reportes.exportar_propio'
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        // 3. Crear Roles y asignar permisos
        $saRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $joRole = Role::firstOrCreate(['name' => 'Jefe de operaciones', 'guard_name' => 'web']);
        $jcRole = Role::firstOrCreate(['name' => 'Jefe de creditos', 'guard_name' => 'web']);
        $asRole = Role::firstOrCreate(['name' => 'Asesor', 'guard_name' => 'web']);

        $saRole->syncPermissions($permisos);
        
        // Asignar permisos específicos a roles para que no fallen las vistas
        $joRole->syncPermissions(['pagos.ver_todos', 'pagos.aprobar', 'pagos.anular', 'pagos.revertir', 'prestamos.desembolsar']);
        $jcRole->syncPermissions(['pagos.ver_todos', 'prestamos.aprobar', 'prestamos.rechazar', 'prestamos.ver_todos']);
        $asRole->syncPermissions(['pagos.crear', 'clientes.crear', 'grupos.crear', 'prestamos.crear']);

        // 4. Crear Usuarios
        $password = Hash::make('test');

        $users = [
            ['name' => 'Super Admin', 'email' => 'sa@test.com', 'role' => 'super_admin'],
            ['name' => 'Jefe Operaciones', 'email' => 'jo@test.com', 'role' => 'Jefe de operaciones'],
            ['name' => 'Jefe Creditos', 'email' => 'jc@test.com', 'role' => 'Jefe de creditos'],
            ['name' => 'Asesor Test', 'email' => 'asesor@test.com', 'role' => 'Asesor'],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                ['name' => $userData['name'], 'password' => $password]
            );
            
            // Limpiar roles previos para evitar duplicados
            $user->roles()->detach();
            $user->assignRole($userData['role']);

            // Si es asesor, crearle la Persona y el registro de Asesor
            if ($userData['role'] === 'Asesor') {
                $persona = \App\Models\Persona::updateOrCreate(
                    ['correo' => $user->email],
                    [
                        'nombre' => 'Asesor',
                        'apellidos' => 'Test',
                        'DNI' => '12345678',
                        'celular' => '999888777',
                        'direccion' => 'Calle Falsa 123',
                    ]
                );

                Asesor::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'persona_id' => $persona->id,
                        'codigo_asesor' => 'ASE-TEST-001',
                        'estado_asesor' => 'activo',
                        'fecha_ingreso' => now(),
                    ]
                );
            }
        }
    }
}
