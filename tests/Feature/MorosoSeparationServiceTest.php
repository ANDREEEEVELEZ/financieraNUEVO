<?php

use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\ProductoFinanciero;
use App\Models\SeparacionCliente;
use App\Models\User;
use App\Domain\Grupos\MorosoSeparationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers de setup
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Crea los roles que Spatie Permission necesita (PrestamoObserver los usa en created()).
 */
function crearRolesSpatie(): void
{
    foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
    }
}

/**
 * Crea el escenario base: 1 grupo, 2 clientes (cumplido + moroso),
 * 1 préstamo grupal activo, 1 cuota grupal, 2 cuotas individuales.
 */
function setupEscenarioBase(): array
{
    crearRolesSpatie();

    $user = User::factory()->create();

    $producto = ProductoFinanciero::factory()->create([
        'penalizacion_separacion' => 0.0000,
    ]);

    $grupo = Grupo::factory()->create(['numero_integrantes' => 2]);

    $clienteCumplido = Cliente::factory()->create();
    $clienteMoroso   = Cliente::factory()->create();

    $grupo->clientes()->attach([
        $clienteCumplido->id => ['fecha_ingreso' => now()],
        $clienteMoroso->id   => ['fecha_ingreso' => now()],
    ]);

    $prestamoGrupal = Prestamo::factory()->create([
        'grupo_id'     => $grupo->id,
        'producto_id'  => $producto->id,
        'estado'       => Prestamo::ESTADO_ACTIVO,
        'tasa_interes' => 15,
        'frecuencia'   => 'quincenal',
        'tipo'         => 'grupal',
    ]);

    // Cuota grupal: 200 capital + 20 interés = 220 (monto_cuota_grupal, ledger-derived)
    $cuotaGrupal = CuotasGrupales::factory()->create([
        'prestamo_id'       => $prestamoGrupal->id,
        'numero_cuota'      => 1,
        'monto_cuota_grupal'=> 220.00,
        'estado_pago'       => 'pendiente',
        'fecha_vencimiento' => now()->subDays(10),
    ]);

    // Cuota individual cliente cumplido: 100 cap + 10 int
    CuotaIndividual::factory()->create([
        'prestamo_id'             => $prestamoGrupal->id,
        'cliente_id'              => $clienteCumplido->id,
        'numero_cuota'            => 1,
        'monto_capital_original'  => 100.00,
        'monto_interes_original'  => 10.00,
        'saldo_capital'           => 100.00,
        'saldo_interes'           => 10.00,
        'estado'                  => 'pendiente',
        'fecha_vencimiento'       => now()->subDays(10),
    ]);

    // Cuota individual cliente moroso: 100 cap + 10 int
    CuotaIndividual::factory()->create([
        'prestamo_id'             => $prestamoGrupal->id,
        'cliente_id'              => $clienteMoroso->id,
        'numero_cuota'            => 1,
        'monto_capital_original'  => 100.00,
        'monto_interes_original'  => 10.00,
        'saldo_capital'           => 100.00,
        'saldo_interes'           => 10.00,
        'estado'                  => 'vencida',
        'fecha_vencimiento'       => now()->subDays(10),
    ]);

    return compact('user', 'producto', 'grupo', 'clienteCumplido', 'clienteMoroso', 'prestamoGrupal', 'cuotaGrupal');
}

// ─────────────────────────────────────────────────────────────────────────────
// Tests existentes (GREEN 4.1, 4.2)
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function () {
    $data = setupEscenarioBase();

    $this->user            = $data['user'];
    $this->producto        = $data['producto'];
    $this->grupo           = $data['grupo'];
    $this->clienteCumplido = $data['clienteCumplido'];
    $this->clienteMoroso   = $data['clienteMoroso'];
    $this->prestamoGrupal  = $data['prestamoGrupal'];
    $this->cuotaGrupal1    = $data['cuotaGrupal'];
});

describe('MorosoSeparationService', function () {

    // ── 4.1 GREEN ──────────────────────────────────────────────────────────

    it('falla si el préstamo no está activo', function () {
        $this->prestamoGrupal->update(['estado' => Prestamo::ESTADO_FINALIZADO]);

        $service = new MorosoSeparationService();
        $service->separar(
            $this->prestamoGrupal->id,
            $this->clienteMoroso->id,
            $this->user->id,
            'Se rehusa a pagar'
        );
    })->throws(RuntimeException::class, 'Solo se puede separar a un integrante de un préstamo activo');

    it('falla si el cliente no tiene cuotas pendientes', function () {
        CuotaIndividual::where('cliente_id', $this->clienteMoroso->id)
            ->update(['estado' => 'pagada', 'saldo_capital' => 0]);

        $service = new MorosoSeparationService();
        $service->separar(
            $this->prestamoGrupal->id,
            $this->clienteMoroso->id,
            $this->user->id,
            'Motivo'
        );
    })->throws(RuntimeException::class, 'no tiene cuotas pendientes');

    it('separa al moroso exitosamente y actualiza saldos', function () {
        $service = new MorosoSeparationService();

        $auditoria = $service->separar(
            $this->prestamoGrupal->id,
            $this->clienteMoroso->id,
            $this->user->id,
            'Cliente inubicable, no responde llamadas'
        );

        // 1. Verificar auditoría
        expect($auditoria)->toBeInstanceOf(SeparacionCliente::class);
        expect($auditoria->estado)->toBe('ejecutada');
        expect((float) $auditoria->deuda_capital)->toBe(100.00);
        expect((float) $auditoria->penalizacion_grupo)->toBe(0.00);

        // 2. Verificar nuevo préstamo individual
        $nuevoPrestamo = Prestamo::find($auditoria->prestamo_nuevo_id);
        expect($nuevoPrestamo)->not->toBeNull();
        expect($nuevoPrestamo->estado)->toBe(Prestamo::ESTADO_SEPARADO);
        expect($nuevoPrestamo->tipo)->toBe('individual');
        expect((float) $nuevoPrestamo->monto_prestado_total)->toBe(100.00);

        // 3. Verificar que el moroso es ex-integrante en el pivot
        $pivot = DB::table('grupo_cliente')
            ->where('grupo_id', $this->grupo->id)
            ->where('cliente_id', $this->clienteMoroso->id)
            ->first();

        expect($pivot->fecha_salida)->not->toBeNull();
        expect($pivot->estado_grupo_cliente)->toBe('separado');

        // 4. Verificar que sus cuotas individuales pasaron al nuevo préstamo
        $cuotasNuevas = CuotaIndividual::where('prestamo_id', $nuevoPrestamo->id)
            ->where('cliente_id', $this->clienteMoroso->id)
            ->get();

        expect($cuotasNuevas)->toHaveCount(1);

        // 5. Verificar que la cuota grupal se redujo (220 - 110 = 110).
        // Ledger-derived (SDD core-contable-seguridad, Slice D): la columna
        // legacy saldo_pendiente fue eliminada; el mismo efecto contable ahora
        // se refleja en monto_cuota_grupal, el campo que SaldoCuotaService lee.
        $cuotaGrupalRenovada = CuotasGrupales::find($this->cuotaGrupal1->id);
        expect((float) $cuotaGrupalRenovada->monto_cuota_grupal)->toBe(110.00);
    });

    // ── 4.2 GREEN — BCMath ─────────────────────────────────────────────────

    it('aplica penalización al grupo si el producto lo indica', function () {
        $this->producto->update(['penalizacion_separacion' => 0.0500]);

        $service  = new MorosoSeparationService();
        $auditoria = $service->separar(
            $this->prestamoGrupal->id,
            $this->clienteMoroso->id,
            $this->user->id,
            'Con penalidad'
        );

        // Penalidad = 5% de 100 capital = 5.00
        // Usando bccomp en lugar de float comparison
        expect(bccomp((string) $auditoria->penalizacion_grupo, '5.00', 2))->toBe(0);
    });

    // ── 4.3 NEW — falla si préstamo es individual ──────────────────────────

    it('falla si préstamo es individual (no grupal)', function () {
        $this->prestamoGrupal->update(['tipo' => 'individual']);

        $service = new MorosoSeparationService();
        $service->separar(
            $this->prestamoGrupal->id,
            $this->clienteMoroso->id,
            $this->user->id,
            'Motivo'
        );
    })->throws(RuntimeException::class, 'La separación solo aplica a préstamos grupales.');

    // ── 4.4 NEW — falla si cliente no pertenece al grupo ──────────────────

    it('falla si cliente no pertenece al grupo', function () {
        $clienteExterno = Cliente::factory()->create();

        $service = new MorosoSeparationService();
        $service->separar(
            $this->prestamoGrupal->id,
            $clienteExterno->id,
            $this->user->id,
            'Motivo'
        );
    })->throws(RuntimeException::class, 'no pertenece al grupo');

    // ── 4.5 NEW — falla por idempotencia ──────────────────────────────────

    it('falla si cliente ya fue separado (idempotencia)', function () {
        SeparacionCliente::create([
            'prestamo_origen_id' => $this->prestamoGrupal->id,
            'prestamo_nuevo_id'  => null,
            'grupo_origen_id'    => $this->grupo->id,
            'cliente_id'         => $this->clienteMoroso->id,
            'ejecutado_por'      => $this->user->id,
            'deuda_capital'      => '100.00',
            'deuda_interes'      => '10.00',
            'deuda_mora'         => '0.00',
            'penalizacion_grupo' => '0.00',
            'motivo'             => 'Separación previa',
            'estado'             => 'ejecutada',
        ]);

        $service = new MorosoSeparationService();
        $service->separar(
            $this->prestamoGrupal->id,
            $this->clienteMoroso->id,
            $this->user->id,
            'Intento duplicado'
        );
    })->throws(RuntimeException::class, 'ya fue separado del préstamo');

    // ── 4.6 NEW — falla si motivo vacío ───────────────────────────────────

    it('falla si motivo vacío', function () {
        $service = new MorosoSeparationService();
        $service->separar(
            $this->prestamoGrupal->id,
            $this->clienteMoroso->id,
            $this->user->id,
            '   '
        );
    })->throws(RuntimeException::class, 'El motivo es obligatorio.');

    // ── 4.7 NEW — falla si último integrante ──────────────────────────────

    it('falla si es el último integrante del grupo', function () {
        // Sacar al cliente cumplido manualmente del pivot
        $this->grupo->todosLosIntegrantes()->updateExistingPivot(
            $this->clienteCumplido->id,
            ['fecha_salida' => now()->toDateString(), 'estado_grupo_cliente' => 'separado']
        );

        $service = new MorosoSeparationService();
        $service->separar(
            $this->prestamoGrupal->id,
            $this->clienteMoroso->id,
            $this->user->id,
            'Motivo'
        );
    })->throws(RuntimeException::class, 'No se puede separar al último integrante activo del grupo');

    // ── 4.8 NEW — invariante contable BCMath ──────────────────────────────

    it('invariante contable: saldo_grupal_pre == saldo_grupal_post + porcion_moroso', function () {
        // Ledger-derived (SDD core-contable-seguridad, Slice D): la columna
        // legacy saldo_pendiente fue eliminada; el invariante ahora se
        // verifica sobre monto_cuota_grupal, el campo que efectivamente se
        // reduce y que SaldoCuotaService lee.
        $saldoPre = CuotasGrupales::where('prestamo_id', $this->prestamoGrupal->id)
            ->get()
            ->reduce(fn ($carry, $cg) => bcadd($carry, (string) $cg->monto_cuota_grupal, 2), '0.00');

        $service = new MorosoSeparationService();
        $service->separar(
            $this->prestamoGrupal->id,
            $this->clienteMoroso->id,
            $this->user->id,
            'Test invariante contable'
        );

        $saldoPost = CuotasGrupales::where('prestamo_id', $this->prestamoGrupal->id)
            ->get()
            ->reduce(fn ($carry, $cg) => bcadd($carry, (string) $cg->monto_cuota_grupal, 2), '0.00');

        $porcionMoroso = CuotaIndividual::where('cliente_id', $this->clienteMoroso->id)
            ->get()
            ->reduce(function ($carry, $ci) {
                return bcadd($carry, bcadd((string) $ci->saldo_capital, (string) $ci->saldo_interes, 2), 2);
            }, '0.00');

        // saldo_pre == saldo_post + porcion_moroso  (escala 2)
        $sumaPost = bcadd($saldoPost, $porcionMoroso, 2);
        expect(bccomp($saldoPre, $sumaPost, 2))->toBe(0);
    });

    // ── 4.9 NEW — Grupo::separarIntegrante falla sin préstamo activo ───────

    it('Grupo::separarIntegrante falla si no hay préstamo activo', function () {
        $this->prestamoGrupal->update(['estado' => Prestamo::ESTADO_FINALIZADO]);

        $this->grupo->separarIntegrante(
            $this->clienteMoroso->id,
            $this->user->id,
            'Motivo'
        );
    })->throws(RuntimeException::class, 'no tiene préstamo grupal activo');

    // ── 4.10 NEW — Grupo::separarIntegrante delega correctamente ──────────

    it('Grupo::separarIntegrante delega correctamente en MorosoSeparationService', function () {
        $resultado = $this->grupo->separarIntegrante(
            $this->clienteMoroso->id,
            $this->user->id,
            'Delegación correcta'
        );

        expect($resultado)->toBeInstanceOf(SeparacionCliente::class);
        expect($resultado->estado)->toBe('ejecutada');
        expect($resultado->cliente_id)->toBe($this->clienteMoroso->id);
    });

});
