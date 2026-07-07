<?php

use App\Filament\Dashboard\Resources\PagoResource\Pages\GrupoDetallePagos;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Scenario 5.6 — parity between PagoResource's inline es_parcialmente_retanqueado
 * branch and GrupoDetallePagos::getCuotaGrupalIdVigente(). Before the fix,
 * getCuotaGrupalIdVigente() ignores the retanqueo-aware branch and can select
 * a different (wrong) vigente cuota than PagoResource would for the same loan.
 */
it('getCuotaGrupalIdVigente selects the same cuota as the retanqueo-aware PagoResource logic', function () {
    $grupo = Grupo::factory()->create();
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado' => Prestamo::ESTADO_ACTIVO,
        'es_parcialmente_retanqueado' => true,
    ]);

    // Cuota 1: still tagged 'vigente' (not yet transitioned to 'cancelada'
    // by the legacy retanqueo write path) but fully covered via retanqueo
    // (estado_pago = 'pagado', saldoPendiente() == 0). A naive
    // whereIn('estado_cuota_grupal', ['vigente','mora']) picks this one up
    // as if it still owed money; the retanqueo-aware branch correctly skips
    // it because estado_pago == 'pagado' excludes it from the candidate set,
    // and saldoPendiente() would be 0 anyway.
    $cuota1 = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'numero_cuota' => 1,
        'monto_cuota_grupal' => 100.00,
        'estado_cuota_grupal' => 'vigente',
        'estado_pago' => 'pagado',
    ]);

    // Cuota 2: the real vigente one — has an outstanding saldo.
    $cuota2 = CuotasGrupales::factory()->create([
        'prestamo_id' => $prestamo->id,
        'numero_cuota' => 2,
        'monto_cuota_grupal' => 100.00,
        'estado_cuota_grupal' => 'vigente',
        'estado_pago' => 'pendiente',
    ]);

    // Replicate PagoResource's es_parcialmente_retanqueado branch (lines ~166-181):
    // all cuotas where estado_pago != 'pagado', filtered by saldoPendiente() > 0.
    $todasLasCuotas = CuotasGrupales::whereHas('prestamo', function ($q) use ($grupo, $prestamo) {
        $q->where('grupo_id', $grupo->id)->where('id', $prestamo->id);
    })->where('estado_pago', '!=', 'pagado')->orderBy('numero_cuota')->get();

    $expected = $todasLasCuotas->first(fn ($c) => $c->saldoPendiente() > 0);

    $page = new GrupoDetallePagos;
    $page->grupo = $grupo;
    $page->prestamo = $prestamo;

    $reflection = new ReflectionMethod($page, 'getCuotaGrupalIdVigente');
    $reflection->setAccessible(true);
    $actualId = $reflection->invoke($page);

    expect($actualId)->toBe($expected?->id)
        ->and($actualId)->toBe($cuota2->id);
});
