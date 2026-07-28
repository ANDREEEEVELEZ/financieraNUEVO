<?php

use App\Filament\Dashboard\Resources\PagoResource\Pages\CreatePago;
use App\Filament\Dashboard\Resources\PagoResource\Pages\EditPago;
use App\Filament\Dashboard\Resources\PagoResource\Pages\GrupoDetallePagos;
use App\Filament\Dashboard\Resources\PagoResource\Pages\ListPagos;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Smoke coverage — CRITICAL-3 (sdd/backend-completion-pagos-auth verify-report
 * #278): each Pago Filament page must mount and render without throwing. This
 * is exactly the guard that would have caught the original V1->V2 relation
 * drift (RelationNotFoundException on detallesPago) and now also guards the
 * Repeater rewrite in Fix 2 (no ->relationship() binding regression).
 */
beforeEach(function () {
    // Table actions build URLs against the current panel; these resources are
    // registered in the 'dashboard' panel, not the default 'admin' panel.
    Filament::setCurrentPanel(Filament::getPanel('dashboard'));

    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create(['active' => true]);
    $this->admin->assignRole('super_admin');
    $this->actingAs($this->admin);

    $this->grupo = Grupo::factory()->create();
    $this->prestamo = Prestamo::factory()->create([
        'grupo_id' => $this->grupo->id,
        'estado' => Prestamo::ESTADO_ACTIVO,
    ]);
    $this->cuota = CuotasGrupales::factory()->create([
        'prestamo_id' => $this->prestamo->id,
        'monto_cuota_grupal' => 100.00,
        'estado_cuota_grupal' => 'vigente',
        'estado_pago' => 'pendiente',
    ]);
    $this->pago = Pago::factory()->create([
        'cuota_grupal_id' => $this->cuota->id,
        'monto_pagado' => 100.00,
        'monto_mora_pagada' => 0,
        'estado_pago' => 'pendiente',
    ]);
});

it('renders ListPagos without exceptions', function () {
    Livewire::test(ListPagos::class)->assertSuccessful();
});

it('renders CreatePago without exceptions', function () {
    Livewire::test(CreatePago::class)->assertSuccessful();
});

it('renders EditPago without exceptions', function () {
    Livewire::test(EditPago::class, ['record' => $this->pago->getRouteKey()])
        ->assertSuccessful();
});

it('renders GrupoDetallePagos without exceptions', function () {
    // Livewire::test() intersects params against typed public properties and
    // assigns them directly before mount() runs — passing raw IDs fails with a
    // TypeError against the `Grupo $grupo` / `Prestamo $prestamo` property
    // types, so the resolved models must be passed instead of their IDs.
    Livewire::test(GrupoDetallePagos::class, [
        'grupo' => $this->grupo,
        'prestamo' => $this->prestamo,
    ])->assertSuccessful();
});
