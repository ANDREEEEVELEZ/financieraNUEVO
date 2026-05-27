<?php

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function cuotaTestMakeAsesor(): array
{
    $user   = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    $token  = $user->createToken('test')->plainTextToken;

    return [$user, $asesor, $token];
}

function cuotaTestMakeSa(): array
{
    $user  = User::factory()->create(['active' => true]);
    $user->assignRole('super_admin');
    $token = $user->createToken('test')->plainTextToken;

    return [$user, $token];
}

/**
 * Create a Prestamo in estado Activo owned by the given Grupo.
 * Returns a CuotaIndividual linked to a Client in that group's portfolio.
 */
function cuotaTestMakeCuota(Asesor $asesor, string $estado = 'pendiente', ?string $fechaVencimiento = null): CuotaIndividual
{
    $grupo    = Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id'         => $grupo->id,
        'estado'           => Prestamo::ESTADO_ACTIVO,
        'fecha_desembolso' => now()->toDateString(),
    ]);
    $cliente  = Cliente::factory()->create();

    return CuotaIndividual::factory()->create([
        'prestamo_id'      => $prestamo->id,
        'cliente_id'       => $cliente->id,
        'estado'           => $estado,
        'fecha_vencimiento'=> $fechaVencimiento ?? now()->addDays(10)->toDateString(),
    ]);
}

// ─── Scenario 1: Asesor lists cuotas → only own clients' cuotas (scope isolation) ──

it('Asesor lists cuotas and only sees cuotas from own portfolio', function () {
    [$userA, $asesorA, $tokenA] = cuotaTestMakeAsesor();
    [$userB, $asesorB, $tokenB] = cuotaTestMakeAsesor();

    // 2 cuotas for Asesor A
    cuotaTestMakeCuota($asesorA);
    cuotaTestMakeCuota($asesorA);

    // 1 cuota for Asesor B (should not appear in A's results)
    cuotaTestMakeCuota($asesorB);

    $response = $this->withToken($tokenA)->getJson('/api/cuotas');

    $response->assertOk()
             ->assertJson(['success' => true])
             ->assertJsonPath('meta.total', 2);
});

// ─── Scenario 2: GET /api/cuotas/hoy → only today's cuotas ─────────────────

it('GET cuotas/hoy returns only cuotas due today for the asesor', function () {
    [$user, $asesor, $token] = cuotaTestMakeAsesor();

    // Due today
    cuotaTestMakeCuota($asesor, 'pendiente', now()->toDateString());
    cuotaTestMakeCuota($asesor, 'pendiente', now()->toDateString());

    // Due in future — should NOT appear
    cuotaTestMakeCuota($asesor, 'pendiente', now()->addDays(5)->toDateString());

    $response = $this->withToken($token)->getJson('/api/cuotas/hoy');

    $response->assertOk()
             ->assertJson(['success' => true]);

    $data = $response->json('data');
    expect(count($data))->toBe(2);

    foreach ($data as $item) {
        expect($item['fecha_vencimiento'])->toBe(now()->toDateString());
    }
});

// ─── Scenario 3: dias_mora = correct positive integer for overdue cuota ─────

it('dias_mora is a positive integer for overdue cuota', function () {
    [$user, $asesor, $token] = cuotaTestMakeAsesor();

    $daysOverdue  = 5;
    $fechaPasada  = now()->subDays($daysOverdue)->toDateString();
    cuotaTestMakeCuota($asesor, 'pendiente', $fechaPasada);

    $response = $this->withToken($token)->getJson('/api/cuotas');

    $response->assertOk();
    $data = $response->json('data');

    $overdue = collect($data)->firstWhere('fecha_vencimiento', $fechaPasada);
    expect($overdue)->not->toBeNull();
    expect($overdue['dias_mora'])->toBe($daysOverdue);
});

// ─── Scenario 4: dias_mora = 0 for not-yet-due cuota ────────────────────────

it('dias_mora is 0 for cuota not yet due', function () {
    [$user, $asesor, $token] = cuotaTestMakeAsesor();

    $fechaFutura = now()->addDays(10)->toDateString();
    cuotaTestMakeCuota($asesor, 'pendiente', $fechaFutura);

    $response = $this->withToken($token)->getJson('/api/cuotas');

    $response->assertOk();
    $data = $response->json('data');

    expect(count($data))->toBe(1);
    expect($data[0]['dias_mora'])->toBe(0);
});

// ─── Scenario 5: SA sees all cuotas ─────────────────────────────────────────

it('SA can list all cuotas without scope restriction', function () {
    [$saUser, $saToken] = cuotaTestMakeSa();

    [, $asesorA] = cuotaTestMakeAsesor();
    [, $asesorB] = cuotaTestMakeAsesor();

    cuotaTestMakeCuota($asesorA);
    cuotaTestMakeCuota($asesorB);

    $response = $this->withToken($saToken)->getJson('/api/cuotas');

    $response->assertOk()
             ->assertJson(['success' => true])
             ->assertJsonPath('meta.total', 2);
});

// ─── Scenario 6: Unauthenticated → 401 ──────────────────────────────────────

it('Unauthenticated request to cuotas returns 401', function () {
    $response = $this->getJson('/api/cuotas');
    $response->assertUnauthorized()
             ->assertJson(['success' => false]);
});
