<?php

use App\Http\Resources\Api\PrestamoResource;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Http\Request;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Resolve PrestamoResource to a plain array (no nested resource serialization needed).
 */
function prestamoResourceArray(Prestamo $prestamo): array
{
    $request = Request::create('/api/prestamos/1', 'GET');

    return (new PrestamoResource($prestamo))->toArray($request);
}

// ─── New scalar fields ───────────────────────────────────────────────────────

it('includes monto_devolver in always-present block', function () {
    $prestamo = Prestamo::factory()->create(['monto_devolver' => 1500.00]);

    $data = prestamoResourceArray($prestamo);

    expect($data)->toHaveKey('monto_devolver');
});

it('includes frecuencia in always-present block', function () {
    $prestamo = Prestamo::factory()->create(['frecuencia' => 'Semanal']);

    $data = prestamoResourceArray($prestamo);

    expect($data)->toHaveKey('frecuencia');
    expect($data['frecuencia'])->toBe('Semanal');
});

it('includes tasa_interes in always-present block', function () {
    $prestamo = Prestamo::factory()->create(['tasa_interes' => 5.00]);

    $data = prestamoResourceArray($prestamo);

    expect($data)->toHaveKey('tasa_interes');
});

it('es_retanqueo is cast to bool', function () {
    $prestamo = Prestamo::factory()->create(['es_retanqueo' => 0]);

    $data = prestamoResourceArray($prestamo);

    expect($data)->toHaveKey('es_retanqueo');
    expect($data['es_retanqueo'])->toBeBool();
    expect($data['es_retanqueo'])->toBeFalse();
});

// ─── FSM booleans — estado Pendiente ────────────────────────────────────────

it('FSM booleans for Pendiente state', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_PENDIENTE]);

    $data = prestamoResourceArray($prestamo);

    expect($data['puede_aprobar'])->toBeTrue();
    expect($data['puede_rechazar'])->toBeTrue();
    expect($data['puede_firmar'])->toBeFalse();
    expect($data['puede_desembolsar'])->toBeFalse();
    expect($data['puede_cancelar'])->toBeFalse();
});

// ─── FSM booleans — estado Aprobado ─────────────────────────────────────────

it('FSM booleans for Aprobado state', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_APROBADO]);

    $data = prestamoResourceArray($prestamo);

    expect($data['puede_aprobar'])->toBeFalse();
    expect($data['puede_rechazar'])->toBeFalse();
    expect($data['puede_firmar'])->toBeTrue();
    expect($data['puede_desembolsar'])->toBeFalse();
    expect($data['puede_cancelar'])->toBeFalse();
});

// ─── FSM booleans — estado Firmado ──────────────────────────────────────────

it('FSM booleans for Firmado state', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_FIRMADO]);

    $data = prestamoResourceArray($prestamo);

    expect($data['puede_aprobar'])->toBeFalse();
    expect($data['puede_rechazar'])->toBeFalse();
    expect($data['puede_firmar'])->toBeFalse();
    expect($data['puede_desembolsar'])->toBeTrue();
    expect($data['puede_cancelar'])->toBeFalse();
});

// ─── FSM booleans — estado Al_Día ───────────────────────────────────────────

it('FSM booleans for Al_Día state', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_AL_DIA]);

    $data = prestamoResourceArray($prestamo);

    expect($data['puede_aprobar'])->toBeFalse();
    expect($data['puede_rechazar'])->toBeFalse();
    expect($data['puede_firmar'])->toBeFalse();
    expect($data['puede_desembolsar'])->toBeFalse();
    expect($data['puede_cancelar'])->toBeTrue();
});

// ─── FSM booleans — estado Finalizado ───────────────────────────────────────

it('FSM booleans for Finalizado state — all false', function () {
    $prestamo = Prestamo::factory()->create(['estado' => Prestamo::ESTADO_FINALIZADO]);

    $data = prestamoResourceArray($prestamo);

    expect($data['puede_aprobar'])->toBeFalse();
    expect($data['puede_rechazar'])->toBeFalse();
    expect($data['puede_firmar'])->toBeFalse();
    expect($data['puede_desembolsar'])->toBeFalse();
    expect($data['puede_cancelar'])->toBeFalse();
});
