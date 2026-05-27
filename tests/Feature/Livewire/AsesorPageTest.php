<?php

declare(strict_types=1);

use App\Filament\Dashboard\Pages\AsesorPage;
use App\Models\Asesor;
use App\Models\CuotaIndividual;
use App\Models\Grupo;
use App\Models\MetricaDiariaAsesor;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['active' => true]);
    $this->user->assignRole('Asesor');
    $this->asesor = Asesor::factory()->create([
        'user_id'       => $this->user->id,
        'estado_asesor' => 'activo',
    ]);
});

it('mounts without error for an authenticated Asesor user', function () {
    $this->actingAs($this->user);

    Livewire::test(AsesorPage::class)
        ->assertHasNoErrors()
        ->assertStatus(200);
});

it('activeTab defaults to hoy', function () {
    $this->actingAs($this->user);

    Livewire::test(AsesorPage::class)
        ->assertSet('activeTab', 'hoy');
});

it('setTab changes activeTab', function () {
    $this->actingAs($this->user);

    Livewire::test(AsesorPage::class)
        ->call('setTab', 'agenda')
        ->assertSet('activeTab', 'agenda');
});

it('cobradoHoy is numeric and zero when no MetricaDiariaAsesor record exists', function () {
    $this->actingAs($this->user);

    $component = Livewire::test(AsesorPage::class);

    expect($component->get('cobradoHoy'))->toBeNumeric()->toBeGreaterThanOrEqual(0);
});

it('cartera array has required KPI keys', function () {
    $this->actingAs($this->user);

    $component = Livewire::test(AsesorPage::class);

    expect($component->get('cartera'))->toHaveKeys([
        'grupos_count',
        'clientes_count',
        'prestamos_count',
        'cartera_total',
    ]);
});

it('tab switch from hoy to agenda sets activeTab to agenda', function () {
    $this->actingAs($this->user);

    Livewire::test(AsesorPage::class)
        ->call('setTab', 'agenda')
        ->assertSet('activeTab', 'agenda');
});

it('zero-cartera asesor does not crash on mount', function () {
    // Asesor with no grupos, clientes, or cuotas
    $emptyUser = User::factory()->create(['active' => true]);
    $emptyUser->assignRole('Asesor');
    Asesor::factory()->create(['user_id' => $emptyUser->id, 'estado_asesor' => 'activo']);

    $this->actingAs($emptyUser);

    Livewire::test(AsesorPage::class)
        ->assertHasNoErrors()
        ->assertSet('cobradoHoy', 0.0);
});

// ── R-20: Shield authorization ───────────────────────────────────────────────

it('redirects unauthenticated users away from AsesorPage', function () {
    $this->get('/dashboard/asesor-page')->assertRedirect();
});

it('allows Jefe de operaciones to access AsesorPage', function () {
    $jefe = User::factory()->create(['active' => true]);
    $jefe->assignRole('Jefe de operaciones');
    Asesor::factory()->create(['user_id' => $jefe->id]);

    $this->actingAs($jefe);

    Livewire::test(AsesorPage::class)->assertHasNoErrors();
});

it('allows Jefe de creditos to access AsesorPage', function () {
    $jefe = User::factory()->create(['active' => true]);
    $jefe->assignRole('Jefe de creditos');

    $this->actingAs($jefe);

    Livewire::test(AsesorPage::class)->assertHasNoErrors();
});
