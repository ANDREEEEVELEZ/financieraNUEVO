<?php

use App\Events\Domain\MoraCondonada;
use App\Models\AjusteDeuda;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Prestamo;
use App\Models\User;
use App\Services\CondonacionMoraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * PR3-T5: MoraCondonada domain event dispatched when CondonacionMoraService::condonar() is called.
 *
 * RED: must fail until MoraCondonada event exists and is dispatched in CondonacionMoraService.
 */
it('dispatches MoraCondonada event when condonar succeeds', function () {
    Event::fake([MoraCondonada::class]);

    $user = User::factory()->create(['active' => true]);
    Auth::login($user);

    $prestamo = Prestamo::factory()->create([
        'estado'          => Prestamo::ESTADO_ACTIVO,
        'cantidad_cuotas' => 4,
    ]);

    $cliente = Cliente::factory()->create();

    $cuotaInd = CuotaIndividual::create([
        'prestamo_id'            => $prestamo->id,
        'cliente_id'             => $cliente->id,
        'numero_cuota'           => 1,
        'monto_capital_original' => 100.00,
        'monto_interes_original' => 10.00,
        'saldo_capital'          => 100.00,
        'saldo_interes'          => 10.00,
        'estado'                 => 'pendiente',
        'fecha_vencimiento'      => now()->addDays(-10)->toDateString(),
    ]);

    app(CondonacionMoraService::class)->condonar(
        $cuotaInd,
        50.00,
        'cliente',
        5.00,
        'Condonación por acuerdo'
    );

    Event::assertDispatched(MoraCondonada::class);
});
