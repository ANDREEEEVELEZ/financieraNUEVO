<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Fase 0.5 — PermissionSeeder actualizado.
 *
 * Cambios respecto a la versión anterior:
 *   NUEVOS PERMISOS:
 *     - pagos.ver_todos      → JC (lectura), JO (gestión)
 *     - pagos.revertir       → Solo JO (deshace un pago aprobado con auditoría)
 *     - retanqueos.ver_todos → JC, JO
 *     - prestamos.firmar     → Solo Asesor (firma contrato, Aprobado → Firmado)
 *     - prestamos.separar_cliente → JC, JO (separar moroso del grupo)
 *     - prestamos.reagrupar  → JC, JO (reagrupación parcial)
 *     - prestamos.reformular → Solo Asesor (reformular solicitud rechazada)
 *     - reportes.exportar_propio → Solo Asesor (exporta su resumen de dashboard)
 *
 *   CORRECCIONES:
 *     - pagos.aprobar: quitado a JC → solo JO verifica transacción bancaria
 *     - pagos.anular: quitado a JC → solo JO
 *     - pagos.crear: quitado a JO → solo Asesor y JC registran solicitud de pago
 *     - clientes.trasladar: ahora también JC (antes solo JO)
 *     - grupos.trasladar_asesor: ahora también JC (antes solo JO)
 *     - prestamos.crear: quitado a JO → solo Asesor y JC (JC asigna a un asesor)
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Permisos de Clientes ───────────────────────────────────────
        $clientePermisos = [
            'clientes.crear',
            'clientes.ver',
            'clientes.editar',
            'clientes.trasladar',       // Reasignar entre asesores (JC, JO)
            // Filament Shield permissions
            'view_any_cliente',
            'create_cliente',
            'update_cliente',
            'delete_cliente',
        ];

        // ─── Permisos de Grupos ─────────────────────────────────────────
        $grupoPermisos = [
            'grupos.crear',
            'grupos.ver',
            'grupos.editar',
            'grupos.trasladar_asesor',   // Reasignar grupo a otro asesor (JC, JO)
            // Filament Shield permissions
            'view_any_grupo',
            'create_grupo',
            'update_grupo',
            'delete_grupo',
        ];

        // ─── Permisos de Préstamos ──────────────────────────────────────
        $prestamoPermisos = [
            'prestamos.crear',           // Crear solicitud (Asesor, JC)
            'prestamos.ver_todos',       // Ver todos los préstamos (JC, JO)
            'prestamos.aprobar',         // Aprobar solicitud (solo JC)
            'prestamos.rechazar',        // Rechazar solicitud (solo JC)
            'prestamos.firmar',          // Firmar contrato: Aprobado → Firmado (solo Asesor)
            'prestamos.desembolsar',     // Desembolsar: Firmado → Activo (solo JO)
            'prestamos.reducir_monto',   // Reducir monto del préstamo (JC, JO)
            'prestamos.reformular',      // Reformular solicitud rechazada (solo Asesor)
            'prestamos.separar_cliente', // Separar cliente moroso del grupo (JC, JO)
            'prestamos.reagrupar',       // Reagrupación parcial (JC, JO)
        ];

        // ─── Permisos de Pagos ──────────────────────────────────────────
        $pagoPermisos = [
            'pagos.crear',               // Registrar solicitud de pago (Asesor, JC)
            'pagos.ver_todos',           // Ver todos los pagos (JC lectura, JO gestión)
            'pagos.aprobar',             // Aprobar pago verificando transacción (solo JO)
            'pagos.anular',              // Anulación lógica de pago (solo JO)
            'pagos.revertir',            // Revertir pago aprobado con auditoría (solo JO)
        ];

        // ─── Permisos de Retanqueos ─────────────────────────────────────
        $retanqueoPermisos = [
            'retanqueos.ver_todos',      // Ver todos los retanqueos (JC, JO)
        ];

        // ─── Permisos de Ajustes de Deuda ───────────────────────────────
        $ajustePermisos = [
            'ajustes.condonar_mora',     // Condonar mora con penalización (JC, JO)
            'ajustes.descontar_capital', // Descuento de capital (JO)
            'ajustes.descontar_interes', // Descuento de interés (JO)
        ];

        // ─── Permisos de Productos Financieros ──────────────────────────
        $productoPermisos = [
            'productos.crear',
            'productos.editar',
            'productos.ver',
            'productos.activar_desactivar',
        ];

        // ─── Permisos de Reportes ───────────────────────────────────────
        $reportePermisos = [
            'reportes.ver_financieros',
            'reportes.ver_mora',
            'reportes.exportar',         // Exportar todo (JC, JO)
            'reportes.exportar_propio',  // Exportar solo info propia (Asesor)
        ];

        // ─── Crear todos los permisos ───────────────────────────────────
        $todosPermisos = array_merge(
            $clientePermisos,
            $grupoPermisos,
            $prestamoPermisos,
            $pagoPermisos,
            $retanqueoPermisos,
            $ajustePermisos,
            $productoPermisos,
            $reportePermisos
        );

        foreach ($todosPermisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        // ─── Roles ─────────────────────────────────────────────────────
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $jefeCreditos = Role::firstOrCreate(['name' => 'Jefe de creditos', 'guard_name' => 'web']);
        $jefeOperaciones = Role::firstOrCreate(['name' => 'Jefe de operaciones', 'guard_name' => 'web']);
        $asesor = Role::firstOrCreate(['name' => 'Asesor', 'guard_name' => 'web']);

        // ─── Super Admin: Todos los permisos ────────────────────────────
        $superAdmin->syncPermissions($todosPermisos);

        // ─── Jefe de Créditos (JC) ─────────────────────────────────────
        // Aprueba/rechaza préstamos, ve retanqueos y pagos (lectura),
        // puede reasignar clientes, separar morosos, reagrupar, condonar.
        // NO aprueba, anula ni revierte pagos.
        $jefeCreditos->syncPermissions([
            // Clientes
            'clientes.crear',
            'clientes.ver',
            'clientes.editar',
            'clientes.trasladar',          // ✅ Reasignar (antes solo JO)
            'view_any_cliente',
            'create_cliente',
            'update_cliente',

            // Grupos
            'grupos.crear',
            'grupos.ver',
            'grupos.editar',
            'grupos.trasladar_asesor',     // ✅ Reasignar (antes solo JO)
            'view_any_grupo',
            'create_grupo',
            'update_grupo',

            // Préstamos
            'prestamos.crear',             // ✅ Crea asignando a un asesor
            'prestamos.ver_todos',
            'prestamos.aprobar',
            'prestamos.rechazar',
            'prestamos.reducir_monto',
            'prestamos.separar_cliente',   // ✅ Nuevo
            'prestamos.reagrupar',         // ✅ Nuevo

            // Pagos — solo registrar y ver, NO aprobar/anular/revertir
            'pagos.crear',                 // ✅ Registra solicitud de pago
            'pagos.ver_todos',             // ✅ Lectura: comportamiento de grupos

            // Retanqueos
            'retanqueos.ver_todos',        // ✅ Nuevo

            // Ajustes
            'ajustes.condonar_mora',

            // Productos
            'productos.ver',

            // Reportes
            'reportes.ver_financieros',
            'reportes.ver_mora',
            'reportes.exportar',
        ]);

        // ─── Jefe de Operaciones (JO) ──────────────────────────────────
        // Aprueba pagos (verifica transacción bancaria), desembolsa préstamos,
        // puede revertir pagos, separar morosos, reagrupar, condonar.
        // NO aprueba/rechaza préstamos. NO crea solicitudes de préstamo.
        $jefeOperaciones->syncPermissions([
            // Clientes
            'clientes.crear',
            'clientes.ver',
            'clientes.editar',
            'clientes.trasladar',
            'view_any_cliente',
            'create_cliente',
            'update_cliente',
            'delete_cliente',

            // Grupos
            'grupos.crear',
            'grupos.ver',
            'grupos.editar',
            'grupos.trasladar_asesor',
            'view_any_grupo',
            'create_grupo',
            'update_grupo',
            'delete_grupo',

            // Préstamos — NO crea, NO aprueba/rechaza
            'prestamos.ver_todos',
            'prestamos.desembolsar',       // Firmado → Activo
            'prestamos.reducir_monto',
            'prestamos.separar_cliente',   // ✅ Nuevo
            'prestamos.reagrupar',         // ✅ Nuevo

            // Pagos — gestión completa
            'pagos.ver_todos',             // ✅ Nuevo
            'pagos.aprobar',               // ✅ Verifica transacción bancaria
            'pagos.anular',
            'pagos.revertir',              // ✅ Nuevo: revierte pago aprobado

            // Retanqueos
            'retanqueos.ver_todos',        // ✅ Nuevo

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

        // ─── Asesor ────────────────────────────────────────────────────
        // Crea clientes, grupos y solicitudes de préstamo (solo propios).
        // Registra solicitudes de pago (estado pendiente).
        // Firma contrato (Aprobado → Firmado).
        // Reformula solicitudes rechazadas.
        // Exporta SOLO su resumen de dashboard.
        $asesor->syncPermissions([
            // Clientes (solo propios via scope)
            'clientes.crear',
            'clientes.ver',
            'clientes.editar',
            'view_any_cliente',
            'create_cliente',
            'update_cliente',

            // Grupos (solo propios via scope)
            'grupos.crear',
            'grupos.ver',
            'grupos.editar',
            'view_any_grupo',
            'create_grupo',
            'update_grupo',

            // Préstamos
            'prestamos.crear',
            'prestamos.firmar',            // ✅ Nuevo: Aprobado → Firmado
            'prestamos.reformular',        // ✅ Nuevo: Rechazado → Reformulado

            // Pagos
            'pagos.crear',                 // Registra solicitud (estado pendiente)

            // Productos
            'productos.ver',

            // Reportes — solo exportar propia información
            'reportes.exportar_propio',    // ✅ Nuevo
        ]);

        $this->command->info('✓ Permisos creados: ' . count($todosPermisos));
        $this->command->info('✓ Roles configurados: super_admin, Jefe de creditos, Jefe de operaciones, Asesor');
    }
}
