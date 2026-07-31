<?php

use App\Console\Commands\ActualizarMorasCommand;
use App\Models\CuotasGrupales;
use App\Models\Mora;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('executes moras:actualizar command and updates snapshot correctly', function () {
    $prestamo = Prestamo::factory()->create(['estado' => 'Activo']);

    $cuota = CuotasGrupales::factory()->create([
        'prestamo_id'        => $prestamo->id,
        'fecha_vencimiento'  => now()->subDays(5)->toDateString(),
        'monto_cuota_grupal' => 500.00,
        'estado_pago'        => 'pendiente',
    ]);

    $this->artisan('moras:actualizar')
        ->assertSuccessful();

    $mora = Mora::where('cuota_grupal_id', $cuota->id)->first();

    expect($mora)->not->toBeNull()
        ->and((int) $mora->dias_atraso)->toBeGreaterThan(0)
        ->and($mora->fecha_snapshot->toDateString())->toBe(now()->toDateString());
});
