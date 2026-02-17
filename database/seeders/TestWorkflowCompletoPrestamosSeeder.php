<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Models\CuotasGrupales;
use App\Models\CuotaIndividual;
use App\Models\MovimientoFinanciero;
use App\Models\User;
use App\Models\Grupo;
use App\Models\Cliente;
use App\Models\Asesor;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fase 8: Testing Manual del Workflow de Préstamos
 * 
 * Este seeder ejecuta pruebas manuales para verificar:
 * 1. Flujo completo de estados
 * 2. Reducción de monto
 * 3. Creación de cuotas
 * 4. Permisos por rol
 * 5. Registro de MovimientoFinanciero
 */
class TestWorkflowCompletoPrestamosSeeder extends Seeder
{
    protected int $passed = 0;
    protected int $failed = 0;
    protected array $errors = [];

    public function run(): void
    {
        $this->command->info("\n" . str_repeat('=', 60));
        $this->command->info('   FASE 8: TESTING DEL WORKFLOW DE PRÉSTAMOS');
        $this->command->info(str_repeat('=', 60));
        $this->command->info('Fecha: ' . now()->toDateTimeString());

        // Test 1: Constantes de Estado
        $this->testConstantesEstado();

        // Test 2: Flujo de Estados
        $this->testFlujoEstados();

        // Test 3: Reducción de Monto
        $this->testReduccionMonto();

        // Test 4: Validaciones de Estado
        $this->testValidacionesEstado();

        // Test 5: Permisos por Rol
        $this->testPermisosPorRol();

        // Test 6: Creación de Cuotas
        $this->testCreacionCuotas();

        // Test 7: MovimientoFinanciero
        $this->testMovimientoFinanciero();

        // Resumen Final
        $this->mostrarResumen();
    }

    protected function test(string $nombre, callable $prueba, string $categoria = 'General'): void
    {
        try {
            $resultado = $prueba();
            if ($resultado === true) {
                $this->command->info("  ✓ {$nombre}");
                $this->passed++;
            } else {
                $this->command->error("  ✗ {$nombre}");
                $this->command->error("    Resultado: " . json_encode($resultado));
                $this->failed++;
                $this->errors[] = "{$categoria}: {$nombre}";
            }
        } catch (\Exception $e) {
            $this->command->error("  ✗ {$nombre}");
            $this->command->error("    Error: " . $e->getMessage());
            $this->failed++;
            $this->errors[] = "{$categoria}: {$nombre} - " . $e->getMessage();
        }
    }

    protected function testConstantesEstado(): void
    {
        $this->command->info("\n--- 1. CONSTANTES DE ESTADO ---");

        $this->test('ESTADO_PENDIENTE está definido', function () {
            return defined(Prestamo::class . '::ESTADO_PENDIENTE');
        }, 'Constantes');

        $this->test('ESTADO_APROBADO está definido', function () {
            return defined(Prestamo::class . '::ESTADO_APROBADO');
        }, 'Constantes');

        $this->test('ESTADO_POR_DESEMBOLSAR está definido', function () {
            return defined(Prestamo::class . '::ESTADO_POR_DESEMBOLSAR');
        }, 'Constantes');

        $this->test('ESTADO_DESEMBOLSADO está definido', function () {
            return defined(Prestamo::class . '::ESTADO_DESEMBOLSADO');
        }, 'Constantes');

        $this->test('ESTADO_ACTIVO está definido', function () {
            return defined(Prestamo::class . '::ESTADO_ACTIVO');
        }, 'Constantes');

        $this->test('ESTADO_FINALIZADO está definido', function () {
            return defined(Prestamo::class . '::ESTADO_FINALIZADO');
        }, 'Constantes');

        $this->test('ESTADO_RECHAZADO está definido', function () {
            return defined(Prestamo::class . '::ESTADO_RECHAZADO');
        }, 'Constantes');
    }

    protected function testFlujoEstados(): void
    {
        $this->command->info("\n--- 2. FLUJO DE ESTADOS ---");

        // Buscar un préstamo en estado Pendiente para prueba
        $prestamoPendiente = Prestamo::where('estado', Prestamo::ESTADO_PENDIENTE)->first();

        if (!$prestamoPendiente) {
            $this->command->warn("  ⚠ No hay préstamos en estado Pendiente para probar");
            $this->command->info("  → Buscando préstamo en cualquier estado para verificar métodos...");

            $prestamo = Prestamo::first();
            if (!$prestamo) {
                $this->command->error("  ✗ No hay préstamos en el sistema");
                $this->failed++;
                return;
            }
        } else {
            $prestamo = $prestamoPendiente;
        }

        $this->test('Préstamo tiene método aprobar()', function () use ($prestamo) {
            return method_exists($prestamo, 'aprobar');
        }, 'Flujo');

        $this->test('Préstamo tiene método desembolsar()', function () use ($prestamo) {
            return method_exists($prestamo, 'desembolsar');
        }, 'Flujo');

        $this->test('Préstamo tiene método reducirMonto()', function () use ($prestamo) {
            return method_exists($prestamo, 'reducirMonto');
        }, 'Flujo');

        $this->test('Estado Pendiente es válido para aprobar', function () use ($prestamo) {
            $prestamo->estado = Prestamo::ESTADO_PENDIENTE;
            return $prestamo->puedeSerAprobado() === true;
        }, 'Flujo');

        $this->test('Estado Por Desembolsar es válido para desembolsar', function () use ($prestamo) {
            $prestamo->estado = Prestamo::ESTADO_POR_DESEMBOLSAR;
            return $prestamo->puedeDesembolsar() === true;
        }, 'Flujo');

        $this->test('Estado Desembolsado NO permite desembolsar de nuevo', function () use ($prestamo) {
            $prestamo->estado = Prestamo::ESTADO_DESEMBOLSADO;
            return $prestamo->puedeDesembolsar() === false;
        }, 'Flujo');
    }

    protected function testReduccionMonto(): void
    {
        $this->command->info("\n--- 3. REDUCCIÓN DE MONTO ---");

        $prestamo = Prestamo::first();
        if (!$prestamo) {
            $this->command->error("  ✗ No hay préstamos para probar reducción de monto");
            $this->failed++;
            return;
        }

        $this->test('Estado Por Desembolsar permite reducir monto', function () use ($prestamo) {
            $prestamo->estado = Prestamo::ESTADO_POR_DESEMBOLSAR;
            return $prestamo->puedeReducirMonto() === true;
        }, 'Reducción');

        $this->test('Estado Pendiente NO permite reducir monto', function () use ($prestamo) {
            $prestamo->estado = Prestamo::ESTADO_PENDIENTE;
            return $prestamo->puedeReducirMonto() === false;
        }, 'Reducción');

        $this->test('Estado Desembolsado NO permite reducir monto', function () use ($prestamo) {
            $prestamo->estado = Prestamo::ESTADO_DESEMBOLSADO;
            return $prestamo->puedeReducirMonto() === false;
        }, 'Reducción');

        $this->test('Estado Activo NO permite reducir monto', function () use ($prestamo) {
            $prestamo->estado = Prestamo::ESTADO_ACTIVO;
            return $prestamo->puedeReducirMonto() === false;
        }, 'Reducción');
    }

    protected function testValidacionesEstado(): void
    {
        $this->command->info("\n--- 4. VALIDACIONES DE ESTADO ---");

        $prestamo = Prestamo::first();
        if (!$prestamo) {
            $this->command->error("  ✗ No hay préstamos para probar validaciones");
            $this->failed++;
            return;
        }

        $this->test('puedeSerAprobado() existe', function () use ($prestamo) {
            return method_exists($prestamo, 'puedeSerAprobado');
        }, 'Validación');

        $this->test('puedeSerRechazado() existe', function () use ($prestamo) {
            return method_exists($prestamo, 'puedeSerRechazado');
        }, 'Validación');

        $this->test('puedeDesembolsar() existe', function () use ($prestamo) {
            return method_exists($prestamo, 'puedeDesembolsar');
        }, 'Validación');

        $this->test('puedeReducirMonto() existe', function () use ($prestamo) {
            return method_exists($prestamo, 'puedeReducirMonto');
        }, 'Validación');

        $this->test('Solo Pendiente puede ser rechazado', function () use ($prestamo) {
            $prestamo->estado = Prestamo::ESTADO_PENDIENTE;
            $puedeRechazarPendiente = $prestamo->puedeSerRechazado();

            $prestamo->estado = Prestamo::ESTADO_POR_DESEMBOLSAR;
            $puedeRechazarPorDesembolsar = $prestamo->puedeSerRechazado();

            return $puedeRechazarPendiente === true && $puedeRechazarPorDesembolsar === false;
        }, 'Validación');
    }

    protected function testPermisosPorRol(): void
    {
        $this->command->info("\n--- 5. PERMISOS POR ROL ---");

        // Verificar roles
        $this->test('Rol Jefe de creditos existe', function () {
            return Role::where('name', 'Jefe de creditos')->exists();
        }, 'Permisos');

        $this->test('Rol Jefe de operaciones existe', function () {
            return Role::where('name', 'Jefe de operaciones')->exists();
        }, 'Permisos');

        $this->test('Rol Asesor existe', function () {
            return Role::where('name', 'Asesor')->exists();
        }, 'Permisos');

        // Verificar usuario JC
        $jc = User::whereHas('roles', fn($q) => $q->where('name', 'Jefe de creditos'))->first();
        if ($jc) {
            $this->test('JC puede aprobar préstamos', function () use ($jc) {
                return $jc->can('prestamos.aprobar');
            }, 'Permisos');

            $this->test('JC puede reducir monto', function () use ($jc) {
                return $jc->can('prestamos.reducir_monto');
            }, 'Permisos');
        } else {
            $this->command->warn("  ⚠ No hay usuario con rol JC para probar");
        }

        // Verificar usuario JO
        $jo = User::whereHas('roles', fn($q) => $q->where('name', 'Jefe de operaciones'))->first();
        if ($jo) {
            $this->test('JO puede desembolsar', function () use ($jo) {
                return $jo->can('prestamos.desembolsar');
            }, 'Permisos');

            $this->test('JO puede reducir monto', function () use ($jo) {
                return $jo->can('prestamos.reducir_monto');
            }, 'Permisos');
        } else {
            $this->command->warn("  ⚠ No hay usuario con rol JO para probar");
        }

        // Verificar usuario Asesor
        $asesor = User::whereHas('roles', fn($q) => $q->where('name', 'Asesor'))->first();
        if ($asesor) {
            $this->test('Asesor puede crear préstamos', function () use ($asesor) {
                return $asesor->can('prestamos.crear') || $asesor->can('create_prestamo');
            }, 'Permisos');

            $this->test('Asesor NO puede aprobar préstamos', function () use ($asesor) {
                return $asesor->can('prestamos.aprobar') === false;
            }, 'Permisos');

            $this->test('Asesor NO puede desembolsar', function () use ($asesor) {
                return $asesor->can('prestamos.desembolsar') === false;
            }, 'Permisos');
        } else {
            $this->command->warn("  ⚠ No hay usuario con rol Asesor para probar");
        }
    }

    protected function testCreacionCuotas(): void
    {
        $this->command->info("\n--- 6. CREACIÓN DE CUOTAS ---");

        // Buscar préstamo desembolsado (debería tener cuotas)
        $prestamoDesembolsado = Prestamo::where('estado', Prestamo::ESTADO_DESEMBOLSADO)
            ->orWhere('estado', Prestamo::ESTADO_ACTIVO)
            ->first();

        if ($prestamoDesembolsado) {
            $this->test('Préstamo desembolsado tiene cuotas grupales', function () use ($prestamoDesembolsado) {
                return $prestamoDesembolsado->cuotasGrupales()->count() > 0;
            }, 'Cuotas');
        } else {
            $this->command->warn("  ⚠ No hay préstamos desembolsados para verificar cuotas");
        }

        // Buscar préstamo pendiente o por desembolsar (NO debería tener cuotas)
        $prestamoPendiente = Prestamo::where('estado', Prestamo::ESTADO_PENDIENTE)
            ->orWhere('estado', Prestamo::ESTADO_POR_DESEMBOLSAR)
            ->first();

        if ($prestamoPendiente) {
            $this->test('Préstamo pendiente NO tiene cuotas grupales', function () use ($prestamoPendiente) {
                return $prestamoPendiente->cuotasGrupales()->count() === 0;
            }, 'Cuotas');
        }

        // Verificar modelo CuotaIndividual existe
        $this->test('Modelo CuotaIndividual existe', function () {
            return class_exists(\App\Models\CuotaIndividual::class);
        }, 'Cuotas');
    }

    protected function testMovimientoFinanciero(): void
    {
        $this->command->info("\n--- 7. MOVIMIENTO FINANCIERO ---");

        $this->test('Modelo MovimientoFinanciero existe', function () {
            return class_exists(\App\Models\MovimientoFinanciero::class);
        }, 'Movimiento');

        // Verificar que hay movimientos registrados
        $totalMovimientos = MovimientoFinanciero::count();
        $this->command->info("  ℹ Total de movimientos en BD: {$totalMovimientos}");

        if ($totalMovimientos > 0) {
            $this->test('Existen movimientos de tipo EGRESO', function () {
                return MovimientoFinanciero::where('tipo', 'EGRESO')->exists();
            }, 'Movimiento');

            $this->test('Movimientos tienen monto > 0', function () {
                return MovimientoFinanciero::where('monto', '>', 0)->exists();
            }, 'Movimiento');
        } else {
            $this->command->warn("  ⚠ No hay movimientos financieros registrados aún");
        }
    }

    protected function mostrarResumen(): void
    {
        $total = $this->passed + $this->failed;
        $porcentaje = $total > 0 ? round(($this->passed / $total) * 100, 1) : 0;

        $this->command->info("\n" . str_repeat('=', 60));
        $this->command->info('                    RESUMEN DE TESTING');
        $this->command->info(str_repeat('=', 60));

        if ($this->failed === 0) {
            $this->command->info("🎉 ¡ÉXITO! Todas las pruebas pasaron");
        } else {
            $this->command->warn("⚠ Algunas pruebas fallaron");
        }

        $this->command->info("\n  Pruebas totales: {$total}");
        $this->command->info("  ✓ Pasadas: {$this->passed}");
        $this->command->error("  ✗ Fallidas: {$this->failed}");
        $this->command->info("  Porcentaje de éxito: {$porcentaje}%");

        if (!empty($this->errors)) {
            $this->command->info("\n--- ERRORES DETALLADOS ---");
            foreach ($this->errors as $error) {
                $this->command->error("  • {$error}");
            }
        }

        $this->command->info("\n" . str_repeat('=', 60));
    }
}
