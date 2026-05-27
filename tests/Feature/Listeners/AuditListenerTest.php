<?php

use App\Events\Domain\MoraCondonada;
use App\Events\Domain\PagoAprobado;
use App\Events\Domain\PagoRevertido;
use App\Events\Domain\PrestamoDesembolsado;
use App\Events\Domain\PrestamoFirmado;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

/**
 * PR3-T6: AuditListener handles all 5 domain events, creating audit_log records.
 *
 * RED: must fail until AuditListener exists, is registered, and all 5 events exist.
 */
it('creates audit_log record when PagoAprobado event is dispatched', function () {
    $user = User::factory()->create(['active' => true]);
    Auth::login($user);

    $pago = Pago::factory()->create([
        'estado_pago'    => 'aprobado',
        'cuota_grupal_id' => null,
    ]);

    event(new PagoAprobado($pago));

    $this->assertDatabaseHas('audit_logs', [
        'accion' => 'pago.aprobado',
    ]);
});

it('creates audit_log record when PagoRevertido event is dispatched', function () {
    $user = User::factory()->create(['active' => true]);
    Auth::login($user);

    $pago = Pago::factory()->create([
        'estado_pago'    => 'pendiente',
        'cuota_grupal_id' => null,
    ]);

    event(new PagoRevertido($pago));

    $this->assertDatabaseHas('audit_logs', [
        'accion' => 'pago.revertido',
    ]);
});

it('creates audit_log record when PrestamoDesembolsado event is dispatched', function () {
    $user = User::factory()->create(['active' => true]);
    Auth::login($user);

    $prestamo = Prestamo::factory()->create([
        'estado'           => Prestamo::ESTADO_ACTIVO,
        'fecha_desembolso' => now()->toDateString(),
    ]);
    $cliente = Cliente::factory()->create();
    // Pre-seed a cuota so DesembolsoListener's safety check returns early
    CuotaIndividual::create([
        'prestamo_id'            => $prestamo->id,
        'cliente_id'             => $cliente->id,
        'numero_cuota'           => 1,
        'monto_capital_original' => 100.00,
        'monto_interes_original' => 10.00,
        'saldo_capital'          => 100.00,
        'saldo_interes'          => 10.00,
        'estado'                 => 'pendiente',
        'fecha_vencimiento'      => now()->addDays(30)->toDateString(),
    ]);

    event(new PrestamoDesembolsado($prestamo));

    $this->assertDatabaseHas('audit_logs', [
        'accion' => 'prestamo.desembolsado',
    ]);
});

it('creates audit_log record when PrestamoFirmado event is dispatched', function () {
    $user = User::factory()->create(['active' => true]);
    Auth::login($user);

    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_FIRMADO]);

    event(new PrestamoFirmado($prestamo));

    $this->assertDatabaseHas('audit_logs', [
        'accion' => 'prestamo.firmado',
    ]);
});

it('creates audit_log record when MoraCondonada event is dispatched', function () {
    $user = User::factory()->create(['active' => true]);
    Auth::login($user);

    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_ACTIVO]);
    $cliente  = Cliente::factory()->create();
    $cuotaInd = CuotaIndividual::create([
        'prestamo_id'            => $prestamo->id,
        'cliente_id'             => $cliente->id,
        'numero_cuota'           => 1,
        'monto_capital_original' => 100.00,
        'monto_interes_original' => 10.00,
        'saldo_capital'          => 100.00,
        'saldo_interes'          => 10.00,
        'estado'                 => 'pendiente',
        'fecha_vencimiento'      => now()->addDays(7)->toDateString(),
    ]);

    event(new MoraCondonada($cuotaInd, 50.0));

    $this->assertDatabaseHas('audit_logs', [
        'accion' => 'mora.condonada',
    ]);
});
