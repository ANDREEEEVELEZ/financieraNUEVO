<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Cliente;
use App\Policies\ClientePolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DeepDiagnosticAsesorSeeder extends Seeder
{
    /**
     * Diagnóstico profundo del sistema de permisos para el Asesor.
     */
    public function run(): void
    {
        $this->command->info('=== DIAGNÓSTICO PROFUNDO: ASESOR Y PERMISOS ===');
        $this->command->info('Fecha: ' . now()->toDateTimeString());

        // 1. Obtener usuario Asesor
        $user = User::where('email', 'asesor@gmail.com')->first();

        if (!$user) {
            $this->command->error('❌ Usuario asesor@gmail.com NO ENCONTRADO');
            return;
        }

        $this->command->info("\n--- 1. INFORMACIÓN DEL USUARIO ---");
        $this->command->line("ID: {$user->id}");
        $this->command->line("Nombre: {$user->name}");
        $this->command->line("Email: {$user->email}");

        // 2. Verificar roles del usuario
        $this->command->info("\n--- 2. ROLES DEL USUARIO ---");
        $roles = $user->roles->pluck('name')->toArray();
        if (empty($roles)) {
            $this->command->error('❌ El usuario NO tiene roles asignados');
        } else {
            foreach ($roles as $role) {
                $this->command->info("  ✓ {$role}");
            }
        }

        // 3. Verificar si tiene rol Asesor específicamente
        $this->command->info("\n--- 3. VERIFICACIÓN DE ROL 'Asesor' ---");
        if ($user->hasRole('Asesor')) {
            $this->command->info("  ✓ Usuario TIENE el rol 'Asesor'");
        } else {
            $this->command->error("  ❌ Usuario NO tiene el rol 'Asesor'");

            // Buscar el rol con diferentes variaciones
            $posiblesRoles = ['Asesor', 'asesor', 'ASESOR'];
            foreach ($posiblesRoles as $roleName) {
                if ($user->hasRole($roleName)) {
                    $this->command->warn("  ⚠ Usuario tiene rol '{$roleName}' (diferente capitalización)");
                }
            }
        }

        // 4. Verificar permisos directos del usuario
        $this->command->info("\n--- 4. PERMISOS DEL USUARIO (directo + por rol) ---");
        $allPermissions = $user->getAllPermissions()->pluck('name')->sort()->values();
        $this->command->line("Total de permisos: {$allPermissions->count()}");

        // Mostrar solo permisos relevantes para Cliente
        $this->command->info("\n  Permisos de Cliente:");
        $clientePerms = $allPermissions->filter(fn($p) => str_contains(strtolower($p), 'cliente'));
        if ($clientePerms->isEmpty()) {
            $this->command->error("    ❌ NO tiene permisos de cliente");
        } else {
            foreach ($clientePerms as $p) {
                $this->command->line("    • {$p}");
            }
        }

        // 5. Verificar permisos específicos usando $user->can()
        $this->command->info("\n--- 5. VERIFICACIÓN CON \$user->can() ---");
        $permisosAVerificar = [
            'view_any_cliente' => 'Ver listado de clientes',
            'view_cliente' => 'Ver detalle de cliente',
            'create_cliente' => 'Crear cliente',
            'update_cliente' => 'Actualizar cliente',
        ];

        foreach ($permisosAVerificar as $permiso => $descripcion) {
            $puede = $user->can($permiso);
            if ($puede) {
                $this->command->info("  ✓ {$permiso}: PERMITIDO ({$descripcion})");
            } else {
                $this->command->error("  ❌ {$permiso}: DENEGADO ({$descripcion})");
            }
        }

        // 6. Verificar directamente con la política
        $this->command->info("\n--- 6. VERIFICACIÓN CON ClientePolicy ---");
        $policy = new ClientePolicy();

        $canViewAny = $policy->viewAny($user);
        $canCreate = $policy->create($user);

        $this->command->info("  ClientePolicy::viewAny() = " . ($canViewAny ? '✓ TRUE' : '❌ FALSE'));
        $this->command->info("  ClientePolicy::create() = " . ($canCreate ? '✓ TRUE' : '❌ FALSE'));

        // 7. Verificar con Gate
        $this->command->info("\n--- 7. VERIFICACIÓN CON Gate::forUser() ---");
        $gateViewAny = Gate::forUser($user)->allows('viewAny', Cliente::class);
        $gateCreate = Gate::forUser($user)->allows('create', Cliente::class);

        $this->command->info("  Gate::allows('viewAny', Cliente) = " . ($gateViewAny ? '✓ TRUE' : '❌ FALSE'));
        $this->command->info("  Gate::allows('create', Cliente) = " . ($gateCreate ? '✓ TRUE' : '❌ FALSE'));

        // 8. Verificar existencia del permiso en la base de datos
        $this->command->info("\n--- 8. VERIFICACIÓN DE PERMISOS EN BASE DE DATOS ---");
        $permisosNecesarios = ['view_any_cliente', 'create_cliente', 'update_cliente', 'view_cliente'];

        foreach ($permisosNecesarios as $nombrePermiso) {
            $permiso = Permission::where('name', $nombrePermiso)->first();
            if ($permiso) {
                $this->command->info("  ✓ Permiso '{$nombrePermiso}' existe (ID: {$permiso->id})");

                // Verificar si está asignado al rol Asesor
                $roleAsesor = Role::where('name', 'Asesor')->first();
                if ($roleAsesor && $roleAsesor->hasPermissionTo($nombrePermiso)) {
                    $this->command->info("    → Asignado al rol 'Asesor'");
                } else {
                    $this->command->warn("    → NO asignado al rol 'Asesor'");
                }
            } else {
                $this->command->error("  ❌ Permiso '{$nombrePermiso}' NO EXISTE en DB");
            }
        }

        // 9. Verificar caché de permisos
        $this->command->info("\n--- 9. INFORMACIÓN ADICIONAL ---");

        // Verificar Asesor asociado
        $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
        if ($asesor) {
            $this->command->info("  ✓ Registro en tabla 'asesors' (ID: {$asesor->id})");
        } else {
            $this->command->error("  ❌ NO tiene registro en tabla 'asesors'");
        }

        // 10. Resumen final
        $this->command->info("\n=== RESUMEN ===");
        $todosPermisos = $user->can('view_any_cliente') &&
            $user->can('create_cliente') &&
            $user->can('update_cliente');

        if ($todosPermisos) {
            $this->command->info("✓ TODOS los permisos están correctos");
            $this->command->info("El problema NO está en los permisos del backend");
            $this->command->warn("Revisar:");
            $this->command->warn("  - Sesión del usuario (cerrar sesión y volver a entrar)");
            $this->command->warn("  - Caché del navegador");
            $this->command->warn("  - JavaScript en el frontend");
            $this->command->warn("  - Filament Actions en ListClientes.php");
        } else {
            $this->command->error("❌ HAY PROBLEMAS con los permisos");
            $this->command->error("Los permisos necesitan ser corregidos");
        }
    }
}
