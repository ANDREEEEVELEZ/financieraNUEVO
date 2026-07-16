<?php

use App\Domain\Prestamos\ReagrupacionParcialService;
use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * SDD core-contable-seguridad, Slice D, Requisito 4.1.
 *
 * Caracterización: ReagrupacionParcialService::calcularDescuentoRetanqueo()
 * migró de leer la columna legacy `saldo_pendiente` (ya eliminada) a derivar
 * el saldo del ledger (SaldoCuotaService::saldoTotal()). Este test prueba que
 * el descuento calculado se deriva correctamente del ledger.
 */
it('calcula el descuento de retanqueo desde el ledger (monto_cuota_grupal, sin pagos aprobados)', function () {
    $user = User::factory()->create(['active' => true]);
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    Auth::login($user);

    $grupoOrigen = Grupo::factory()->create([
        'asesor_id' => $asesor->id,
        'estado_grupo' => 'Activo',
        'numero_integrantes' => 5,
    ]);

    // estado Finalizado para que tienePrestamosActivos() sea false y el traslado sea permitido
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupoOrigen->id,
        'estado' => Prestamo::ESTADO_FINALIZADO,
    ]);

    // Última cuota pendiente: ledger dice 100.00 (monto_cuota_grupal, sin
    // pagos aprobados).
    CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'numero_cuota' => 1,
        'monto_cuota_grupal' => 100,
        'estado_pago' => 'pendiente',
    ]);

    $clientes = Cliente::factory()->count(5)->create();
    $rows = $clientes->map(fn ($c) => [
        'grupo_id' => $grupoOrigen->id,
        'cliente_id' => $c->id,
        'fecha_ingreso' => now()->toDateString(),
        'fecha_salida' => null,
        'rol' => 'Integrante',
        'estado_grupo_cliente' => 'Activo',
        'created_at' => now(),
        'updated_at' => now(),
    ])->toArray();
    DB::table('grupo_cliente')->insert($rows);

    $clienteTrasladado = $clientes->first();

    $service = new ReagrupacionParcialService;
    $reagrupacion = $service->reagrupar(
        $prestamo,
        [$clienteTrasladado->id],
        'retanqueo',
        'Test descuento ledger-derived'
    );

    // calcularDescuentoRetanqueo cuenta los integrantes del grupo DESPUÉS del
    // traslado (paso 2 de reagrupar() ocurre antes del cálculo de descuento):
    // quedan 4 de 5 en el grupo origen -> proporcion = 1/4 = 0.25;
    // ledger saldoTotal = 100.00 -> descuento = 25.00.
    expect((float) $reagrupacion->monto_descuento)->toBe(25.00);
});
