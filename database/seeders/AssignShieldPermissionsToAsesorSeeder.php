<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AssignShieldPermissionsToAsesorSeeder extends Seeder
{
    /**
     * Asigna los permisos correctos de Filament Shield al rol Asesor.
     */
    public function run(): void
    {
        $this->command->info('=== Asignando Permisos de Shield al Rol Asesor ===');

        // Obtener el rol Asesor
        $asesor = Role::where('name', 'Asesor')->first();

        if (!$asesor) {
            $this->command->error('❌ El rol Asesor no existe');
            return;
        }

        // Listar todos los permisos disponibles para Cliente y Grupo
        $this->command->info("\n=== Permisos Disponibles para Cliente ===");
        $permisosCliente = Permission::where('name', 'like', '%cliente%')->pluck('name')->toArray();
        foreach ($permisosCliente as $p) {
            $this->command->line("  • {$p}");
        }

        $this->command->info("\n=== Permisos Disponibles para Grupo ===");
        $permisosGrupo = Permission::where('name', 'like', '%grupo%')->pluck('name')->toArray();
        foreach ($permisosGrupo as $p) {
            $this->command->line("  • {$p}");
        }

        // Definir los permisos que el Asesor debe tener
        $permisosAsesor = [
            // Cliente - Permisos básicos de CRUD (sin delete)
            'view_cliente',
            'view_any_cliente',
            'create_cliente',
            'update_cliente',

            // Grupo - Permisos básicos de CRUD (sin delete)
            'view_grupo',
            'view_any_grupo',
            'create_grupo',
            'update_grupo',

            // Préstamo - Solo ver y crear
            'view_prestamo',
            'view_any_prestamo',
            'create_prestamo',

            // Pago - Solo ver y crear
            'view_pago',
            'view_any_pago',
            'create_pago',

            // Producto Financiero - Solo ver
            'view_producto_financiero',
            'view_any_producto_financiero',

            // Páginas
            'page_AsesorPage',
        ];

        $this->command->info("\n=== Permisos a Asignar al Asesor ===");

        $permisosAsignados = [];
        $permisosNoEncontrados = [];

        foreach ($permisosAsesor as $nombrePermiso) {
            $permiso = Permission::where('name', $nombrePermiso)->first();

            if ($permiso) {
                $permisosAsignados[] = $nombrePermiso;
                $this->command->info("  ✓ {$nombrePermiso}");
            } else {
                $permisosNoEncontrados[] = $nombrePermiso;
                $this->command->warn("  ⚠ {$nombrePermiso} - NO EXISTE");
            }
        }

        // También incluir los permisos personalizados existentes
        $permisosCustom = [
            'clientes.crear',
            'clientes.ver',
            'clientes.editar',
            'grupos.crear',
            'grupos.ver',
            'grupos.editar',
            'prestamos.crear',
            'pagos.crear',
            'productos.ver',
        ];

        foreach ($permisosCustom as $nombrePermiso) {
            $permiso = Permission::where('name', $nombrePermiso)->first();
            if ($permiso) {
                $permisosAsignados[] = $nombrePermiso;
                $this->command->info("  ✓ {$nombrePermiso} (custom)");
            }
        }

        // Asignar todos los permisos encontrados al rol Asesor
        $asesor->syncPermissions($permisosAsignados);

        $this->command->info("\n=== Resultado ===");
        $this->command->info("✓ Permisos asignados: " . count($permisosAsignados));

        if (!empty($permisosNoEncontrados)) {
            $this->command->warn("⚠ Permisos no encontrados: " . count($permisosNoEncontrados));
            foreach ($permisosNoEncontrados as $p) {
                $this->command->warn("  - {$p}");
            }
        }

        // Verificar permisos finales del Asesor
        $this->command->info("\n=== Permisos Finales del Asesor ===");
        $asesor->refresh();
        $permisosFinales = $asesor->permissions->pluck('name')->sort()->values();
        foreach ($permisosFinales as $p) {
            $this->command->line("  • {$p}");
        }

        $this->command->info("\n=== Proceso Completado ===");
        $this->command->info("Ahora debes:");
        $this->command->info("1. Ejecutar: php artisan optimize:clear");
        $this->command->info("2. Cerrar sesión y volver a iniciar sesión con el Asesor");
    }
}
