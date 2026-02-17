<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class TestAsesorPermissionsSeeder extends Seeder
{
    /**
     * Verifica y muestra los permisos del rol Asesor.
     */
    public function run(): void
    {
        $this->command->info('=== Verificación de Permisos del Rol Asesor ===');

        // Obtener el rol Asesor
        $asesor = Role::where('name', 'Asesor')->first();

        if (!$asesor) {
            $this->command->error('❌ El rol Asesor no existe');
            return;
        }

        $this->command->info('✓ Rol Asesor encontrado');

        // Listar todos los permisos del Asesor
        $permisos = $asesor->permissions->pluck('name')->sort()->values();

        $this->command->info("\n=== Permisos Asignados al Asesor ({$permisos->count()}) ===");
        foreach ($permisos as $permiso) {
            $this->command->line("  • {$permiso}");
        }

        // Verificar permisos críticos para Clientes y Grupos
        $permisosCriticos = [
            'view_any_cliente',
            'create_cliente',
            'update_cliente',
            'view_any_grupo',
            'create_grupo',
            'update_grupo',
        ];

        $this->command->info("\n=== Verificación de Permisos Críticos ===");
        foreach ($permisosCriticos as $permiso) {
            if ($asesor->hasPermissionTo($permiso)) {
                $this->command->info("  ✓ {$permiso}");
            } else {
                $this->command->error("  ❌ {$permiso} - FALTA");
            }
        }

        // Verificar un usuario Asesor de prueba
        $usuarioAsesor = User::whereHas('roles', function ($q) {
            $q->where('name', 'Asesor');
        })->first();

        if ($usuarioAsesor) {
            $this->command->info("\n=== Usuario Asesor de Prueba: {$usuarioAsesor->name} ===");
            $this->command->info("Email: {$usuarioAsesor->email}");

            // Verificar permisos del usuario
            $this->command->info("\n=== Verificación de Permisos del Usuario ===");
            foreach ($permisosCriticos as $permiso) {
                if ($usuarioAsesor->can($permiso)) {
                    $this->command->info("  ✓ {$permiso}");
                } else {
                    $this->command->error("  ❌ {$permiso} - NO PUEDE");
                }
            }

            // Verificar rol
            if ($usuarioAsesor->hasRole('Asesor')) {
                $this->command->info("\n  ✓ Usuario tiene el rol 'Asesor'");
            } else {
                $this->command->error("\n  ❌ Usuario NO tiene el rol 'Asesor'");
            }
        } else {
            $this->command->warn("\n⚠ No se encontró ningún usuario con el rol Asesor");
        }
    }
}
