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
            // Filament Shield permissions
            'view_any_prestamo',
            'view_prestamo',
            'create_prestamo',
            'update_prestamo',
            'delete_prestamo',
            'delete_any_prestamo',
            'force_delete_prestamo',
            'force_delete_any_prestamo',
            'restore_prestamo',
            'restore_any_prestamo',
            'replicate_prestamo',
            'reorder_prestamo',
        ];

        // ─── Permisos de Pagos ──────────────────────────────────────────
        $pagoPermisos = [
            'pagos.crear',               // Registrar solicitud de pago (Asesor, JC)
            'pagos.ver_todos',           // Ver todos los pagos (JC lectura, JO gestión)
            'pagos.aprobar',             // Aprobar pago verificando transacción (solo JO)
            'pagos.anular',              // Anulación lógica de pago (solo JO)
            'pagos.revertir',            // Revertir pago aprobado con auditoría (solo JO)
            // Filament Shield permissions
            'view_any_pago',
            'view_pago',
            'create_pago',
            'update_pago',
            'delete_pago',
            'delete_any_pago',
            'force_delete_pago',
            'force_delete_any_pago',
            'restore_pago',
            'restore_any_pago',
            'replicate_pago',
            'reorder_pago',
        ];

        // ─── Permisos de Retanqueos ─────────────────────────────────────
        $retanqueoPermisos = [
            'retanqueos.ver_todos',      // Ver todos los retanqueos (JC, JO)
            // Filament Shield permissions
            'view_any_retanqueo',
            'view_retanqueo',
            'create_retanqueo',
            'update_retanqueo',
            'delete_retanqueo',
            'delete_any_retanqueo',
            'force_delete_retanqueo',
            'force_delete_any_retanqueo',
            'restore_retanqueo',
            'restore_any_retanqueo',
            'replicate_retanqueo',
            'reorder_retanqueo',
        ];

        // ─── Permisos de Ajustes de Deuda ───────────────────────────────
        $ajustePermisos = [
            'ajustes.condonar_mora',     // Condonar mora con penalización (JC, JO)
            'ajustes.descontar_capital', // Descuento de capital (JO)
            'ajustes.descontar_interes', // Descuento de interés (JO)
        ];

        // ─── Permisos Slice 5b-financial (laravel-security-hardening) ───
        // Shield-style CRUD permissions for Mora, CuotaIndividual,
        // CuotasGrupales, AplicacionPago, AjusteDeuda — these 5 models have
        // no existing Filament Resource of their own and currently ZERO
        // authorization gate anywhere in the codebase (see each Policy
        // class's docblock for the grep-derived evidence). Granted to ALL
        // FOUR roles below to preserve that de-facto "open to any
        // authenticated user" behavior — spec Requirement 4.3 forbids this
        // refactor from silently tightening access. Not yet referenced by
        // any Filament call site (that wiring is Slice 5c-5g, later PRs).
        $financieroPermisos = [
            'view_any_mora', 'view_mora', 'create_mora', 'update_mora', 'delete_mora',
            'view_any_cuota_individual', 'view_cuota_individual', 'create_cuota_individual', 'update_cuota_individual', 'delete_cuota_individual',
            'view_any_cuotas_grupales', 'view_cuotas_grupales', 'create_cuotas_grupales', 'update_cuotas_grupales', 'delete_cuotas_grupales',
            'view_any_aplicacion_pago', 'view_aplicacion_pago', 'create_aplicacion_pago', 'update_aplicacion_pago', 'delete_aplicacion_pago',
            'view_any_ajuste_deuda', 'view_ajuste_deuda', 'create_ajuste_deuda', 'update_ajuste_deuda', 'delete_ajuste_deuda',
        ];

        // ─── Permisos Slice 5b-lifecycle (laravel-security-hardening) ───
        // Shield-style CRUD permissions for PrestamoIndividual,
        // SeparacionCliente, Reagrupacion, RetanqueoIndividual. Grep across
        // app/Filament and app/Domain/Prestamos + app/Domain/Grupos confirmed
        // zero existing authorization gate for any of these 4 models: the
        // Filament call sites that touch PrestamoIndividual/RetanqueoIndividual
        // are plain data reads/writes (never a hasRole/hasAnyRole/visible/
        // hidden/authorize/canX check), and SeparacionCliente/Reagrupacion
        // have no Filament Resource of their own — written only by ungated
        // domain services (MorosoSeparationService, RetanqueoWorkflowService/
        // RetanqueoEjecucionService). Granted to ALL FOUR roles below to
        // preserve that de-facto "open to any authenticated user" behavior —
        // spec Requirement 4.3 forbids this refactor from silently tightening
        // access. Not yet referenced by any Filament call site (that wiring
        // is Slice 5c-5g, later PRs).
        $lifecyclePermisos = [
            'view_any_prestamo_individual', 'view_prestamo_individual', 'create_prestamo_individual', 'update_prestamo_individual', 'delete_prestamo_individual',
            'view_any_separacion_cliente', 'view_separacion_cliente', 'create_separacion_cliente', 'update_separacion_cliente', 'delete_separacion_cliente',
            'view_any_reagrupacion', 'view_reagrupacion', 'create_reagrupacion', 'update_reagrupacion', 'delete_reagrupacion',
            'view_any_retanqueo_individual', 'view_retanqueo_individual', 'create_retanqueo_individual', 'update_retanqueo_individual', 'delete_retanqueo_individual',
        ];

        // ─── Permisos de Productos Financieros ──────────────────────────
        $productoPermisos = [
            'productos.crear',
            'productos.editar',
            'productos.ver',
            'productos.activar_desactivar',
            // Filament Shield permissions
            'view_any_producto::financiero',
            'view_producto::financiero',
            'create_producto::financiero',
            'update_producto::financiero',
            'delete_producto::financiero',
            'delete_any_producto::financiero',
            'force_delete_producto::financiero',
            'force_delete_any_producto::financiero',
            'restore_producto::financiero',
            'restore_any_producto::financiero',
            'replicate_producto::financiero',
            'reorder_producto::financiero',
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
            $financieroPermisos,
            $lifecyclePermisos,
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

        // ─── Super Admin: Todos los permisos (incluyendo los de Filament Shield) ──
        $superAdmin->syncPermissions(Permission::all());

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
            'view_any_prestamo',
            'view_prestamo',
            'create_prestamo',
            'reorder_prestamo',

            // Pagos — solo registrar y ver, NO aprobar/anular/revertir
            'pagos.crear',                 // ✅ Registra solicitud de pago
            'pagos.ver_todos',             // ✅ Lectura: comportamiento de grupos
            'view_any_pago',
            'view_pago',

            // Retanqueos
            'retanqueos.ver_todos',        // ✅ Nuevo
            'view_any_retanqueo',
            'view_retanqueo',

            // Ajustes
            'ajustes.condonar_mora',

            // Mora, Cuotas, Aplicaciones de Pago, Ajustes de Deuda (Slice 5b-financial)
            ...$financieroPermisos,

            // PrestamoIndividual, SeparacionCliente, Reagrupacion, RetanqueoIndividual (Slice 5b-lifecycle)
            ...$lifecyclePermisos,

            // Productos
            'productos.ver',
            'view_any_producto::financiero',
            'view_producto::financiero',

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
            'view_any_prestamo',
            'view_prestamo',
            'reorder_prestamo',

            // Pagos — gestión completa
            'pagos.ver_todos',             // ✅ Nuevo
            'pagos.aprobar',               // ✅ Verifica transacción bancaria
            'pagos.anular',
            'pagos.revertir',              // ✅ Nuevo: revierte pago aprobado
            'view_any_pago',
            'view_pago',
            'create_pago',
            'update_pago',
            'delete_pago',
            'reorder_pago',

            // Retanqueos
            'retanqueos.ver_todos',        // ✅ Nuevo
            'view_any_retanqueo',
            'view_retanqueo',
            'create_retanqueo',
            'reorder_retanqueo',

            // Ajustes
            'ajustes.condonar_mora',
            'ajustes.descontar_capital',

            // Mora, Cuotas, Aplicaciones de Pago, Ajustes de Deuda (Slice 5b-financial)
            ...$financieroPermisos,

            // PrestamoIndividual, SeparacionCliente, Reagrupacion, RetanqueoIndividual (Slice 5b-lifecycle)
            ...$lifecyclePermisos,

            // Productos
            'productos.ver',
            'view_any_producto::financiero',
            'view_producto::financiero',

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
            'view_any_prestamo',
            'view_prestamo',
            'create_prestamo',
            'reorder_prestamo',

            // Pagos
            'pagos.crear',                 // Registra solicitud (estado pendiente)
            'view_any_pago',
            'view_pago',
            'create_pago',
            'reorder_pago',

            // Mora, Cuotas, Aplicaciones de Pago, Ajustes de Deuda (Slice 5b-financial)
            ...$financieroPermisos,

            // PrestamoIndividual, SeparacionCliente, Reagrupacion, RetanqueoIndividual (Slice 5b-lifecycle)
            ...$lifecyclePermisos,

            // Productos
            'productos.ver',
            'view_any_producto::financiero',
            'view_producto::financiero',

            // Reportes — solo exportar propia información
            'reportes.exportar_propio',    // ✅ Nuevo
        ]);

        $this->command->info('✓ Permisos creados: ' . count($todosPermisos));
        $this->command->info('✓ Roles configurados: super_admin, Jefe de creditos, Jefe de operaciones, Asesor');
    }
}
