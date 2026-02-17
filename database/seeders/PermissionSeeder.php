<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Crea permisos granulares y los asigna a roles.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear permisos de Clientes (custom + Filament Shield)
        $clientePermisos = [
            'clientes.crear',
            'clientes.ver',
            'clientes.editar',
            'clientes.trasladar', // Solo JO
            // Filament Shield permissions
            'view_any_cliente',
            'create_cliente',
            'update_cliente',
            'delete_cliente',
        ];

        // Crear permisos de Grupos (custom + Filament Shield)
        $grupoPermisos = [
            'grupos.crear',
            'grupos.ver',
            'grupos.editar',
            'grupos.trasladar_asesor', // Solo JO
            // Filament Shield permissions
            'view_any_grupo',
            'create_grupo',
            'update_grupo',
            'delete_grupo',
        ];

        // Crear permisos de Préstamos
        $prestamoPermisos = [
            'prestamos.crear',
            'prestamos.ver_todos',
            'prestamos.aprobar',
            'prestamos.reducir_monto',
            'prestamos.desembolsar',
            'prestamos.rechazar',
        ];

        // Crear permisos de Pagos
        $pagoPermisos = [
            'pagos.crear',
            'pagos.aprobar',
            'pagos.anular', // Anulación lógica, no eliminación física
        ];

        // Crear permisos de Ajustes de Deuda
        $ajustePermisos = [
            'ajustes.condonar_mora',
            'ajustes.descontar_capital',
            'ajustes.descontar_interes',
        ];

        // Crear permisos de Productos Financieros
        $productoPermisos = [
            'productos.crear',
            'productos.editar',
            'productos.ver',
            'productos.activar_desactivar',
        ];

        // Crear permisos de Reportes
        $reportePermisos = [
            'reportes.ver_financieros',
            'reportes.ver_mora',
            'reportes.exportar',
        ];

        // Crear todos los permisos
        $todosPermisos = array_merge(
            $clientePermisos,
            $grupoPermisos,
            $prestamoPermisos,
            $pagoPermisos,
            $ajustePermisos,
            $productoPermisos,
            $reportePermisos
        );

        foreach ($todosPermisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        // Obtener o crear roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $jefeCreditos = Role::firstOrCreate(['name' => 'Jefe de creditos', 'guard_name' => 'web']);
        $jefeOperaciones = Role::firstOrCreate(['name' => 'Jefe de operaciones', 'guard_name' => 'web']);
        $asesor = Role::firstOrCreate(['name' => 'Asesor', 'guard_name' => 'web']);

        // Super Admin: Todos los permisos
        $superAdmin->syncPermissions($todosPermisos);

        // Jefe de Créditos (JC)
        $jefeCreditos->syncPermissions([
            // Clientes
            'clientes.crear',
            'clientes.ver',
            'clientes.editar',
            'view_any_cliente',
            'create_cliente',
            'update_cliente',

            // Grupos
            'grupos.crear',
            'grupos.ver',
            'grupos.editar',
            'view_any_grupo',
            'create_grupo',
            'update_grupo',

            // Préstamos
            'prestamos.crear',
            'prestamos.ver_todos',
            'prestamos.aprobar',
            'prestamos.reducir_monto',
            'prestamos.rechazar',

            // Pagos
            'pagos.crear',
            'pagos.aprobar',
            'pagos.anular',

            // Ajustes
            'ajustes.condonar_mora',

            // Productos
            'productos.ver',

            // Reportes
            'reportes.ver_financieros',
            'reportes.ver_mora',
            'reportes.exportar',
        ]);

        // Jefe de Operaciones (JO)
        $jefeOperaciones->syncPermissions([
            // Clientes
            'clientes.crear',
            'clientes.ver',
            'clientes.editar',
            'clientes.trasladar', // Solo JO
            'view_any_cliente',
            'create_cliente',
            'update_cliente',
            'delete_cliente',

            // Grupos
            'grupos.crear',
            'grupos.ver',
            'grupos.editar',
            'grupos.trasladar_asesor', // Solo JO
            'view_any_grupo',
            'create_grupo',
            'update_grupo',
            'delete_grupo',

            // Préstamos
            'prestamos.crear',
            'prestamos.ver_todos',
            'prestamos.reducir_monto', // Puede reducir monto
            'prestamos.desembolsar',

            // Pagos
            'pagos.crear',
            'pagos.aprobar',
            'pagos.anular',

            // Ajustes
            'ajustes.condonar_mora',
            'ajustes.descontar_capital',

            // Productos
            'productos.ver',

            // Reportes
            'reportes.ver_financieros',
            'reportes.ver_mora',
            'reportes.exportar',
        ]);

        // Asesor
        $asesor->syncPermissions([
            // Clientes (solo sus clientes)
            'clientes.crear',
            'clientes.ver',
            'clientes.editar',
            'view_any_cliente',
            'create_cliente',
            'update_cliente',

            // Grupos (solo sus grupos)
            'grupos.crear',
            'grupos.ver',
            'grupos.editar',
            'view_any_grupo',
            'create_grupo',
            'update_grupo',

            // Préstamos (solo sus grupos)
            'prestamos.crear',

            // Pagos
            'pagos.crear',

            // Productos
            'productos.ver',
        ]);

        $this->command->info('✓ Permisos creados: ' . count($todosPermisos));
        $this->command->info('✓ Roles configurados: super_admin, Jefe de creditos, Jefe de operaciones, Asesor');
    }
}
