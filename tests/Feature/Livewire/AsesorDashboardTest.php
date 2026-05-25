<?php

declare(strict_types=1);

use App\Filament\Dashboard\Pages\AsesorDashboard;
use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\ClienteScoring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['active' => true]);
    $this->user->assignRole('Asesor');
    $this->asesor = Asesor::factory()->create([
        'user_id'       => $this->user->id,
        'estado_asesor' => 'activo',
    ]);
});

// ── T-05 RED: Core component tests ──────────────────────────────────────────

it('mounts without error for an authenticated Asesor user', function () {
    $this->actingAs($this->user);

    Livewire::test(AsesorDashboard::class)
        ->assertHasNoErrors()
        ->assertStatus(200);
});

it('activeTab defaults to cobranza', function () {
    $this->actingAs($this->user);

    Livewire::test(AsesorDashboard::class)
        ->assertSet('activeTab', 'cobranza');
});

it('period defaults to mes', function () {
    $this->actingAs($this->user);

    Livewire::test(AsesorDashboard::class)
        ->assertSet('period', 'mes');
});

it('meta array has required keys after mount', function () {
    $this->actingAs($this->user);

    $component = Livewire::test(AsesorDashboard::class);

    expect($component->get('meta'))
        ->toHaveKey('cobrado')
        ->toHaveKey('meta')
        ->toHaveKey('pct');
});

it('setTab changes activeTab', function () {
    $this->actingAs($this->user);

    Livewire::test(AsesorDashboard::class)
        ->call('setTab', 'cartera')
        ->assertSet('activeTab', 'cartera');
});

it('setPeriod changes period to semana', function () {
    $this->actingAs($this->user);

    Livewire::test(AsesorDashboard::class)
        ->call('setPeriod', 'semana')
        ->assertSet('period', 'semana');
});

it('setPeriod ignores invalid value trim', function () {
    $this->actingAs($this->user);

    // 'trim' is display-only and must be ignored by setPeriod
    Livewire::test(AsesorDashboard::class)
        ->call('setPeriod', 'trim')
        ->assertSet('period', 'mes'); // remains at default
});

// ── T-09: Extended tests ─────────────────────────────────────────────────────

it('isOperacionesLocked is true for Asesor role', function () {
    $this->actingAs($this->user);

    $component = Livewire::test(AsesorDashboard::class);

    expect($component->get('isOperacionesLocked'))->toBeTrue();
});

it('operaciones tab shows acceso restringido for Asesor role', function () {
    $this->actingAs($this->user);

    Livewire::test(AsesorDashboard::class)
        ->call('setTab', 'operaciones')
        ->assertSee('Acceso restringido');
});

it('scoring card shows empty state when no vigente ClienteScoring records exist', function () {
    // Asesor has a cliente but no vigente scoring records
    $cliente = Cliente::factory()->create([
        'asesor_id'      => $this->asesor->id,
        'estado_cliente' => 'activo',
    ]);
    // Create a non-vigente scoring record — should be ignored
    ClienteScoring::create([
        'cliente_id' => $cliente->id,
        'score'      => 50,
        'grado'      => 'C',
        'vigente'    => false,
    ]);

    $this->actingAs($this->user);

    Livewire::test(AsesorDashboard::class)
        ->call('setTab', 'colocacion')
        ->assertSee('Clasificación pendiente');
});

it('scoring card renders bar segments when vigente ClienteScoring records exist', function () {
    $clienteA = Cliente::factory()->create(['asesor_id' => $this->asesor->id]);
    $clienteB = Cliente::factory()->create(['asesor_id' => $this->asesor->id]);
    $clienteC = Cliente::factory()->create(['asesor_id' => $this->asesor->id]);

    // 3 grado=A vigente
    foreach ([$clienteA, $clienteB, $clienteC] as $c) {
        ClienteScoring::create(['cliente_id' => $c->id, 'score' => 80, 'grado' => 'A', 'vigente' => true]);
    }

    // 2 grado=C vigente
    $clienteD = Cliente::factory()->create(['asesor_id' => $this->asesor->id]);
    $clienteE = Cliente::factory()->create(['asesor_id' => $this->asesor->id]);
    ClienteScoring::create(['cliente_id' => $clienteD->id, 'score' => 40, 'grado' => 'C', 'vigente' => true]);
    ClienteScoring::create(['cliente_id' => $clienteE->id, 'score' => 45, 'grado' => 'C', 'vigente' => true]);

    $this->actingAs($this->user);

    $component = Livewire::test(AsesorDashboard::class)
        ->call('setTab', 'colocacion');

    // gradoData should be populated
    expect($component->get('gradoData'))->not->toBeEmpty();

    // No empty state message
    $component->assertDontSee('Clasificación pendiente');
});

it('data isolation: asesor A does not see asesor B data', function () {
    // Asesor B with their own cliente and scoring
    $userB = User::factory()->create(['active' => true]);
    $userB->assignRole('Asesor');
    $asesorB = Asesor::factory()->create(['user_id' => $userB->id, 'estado_asesor' => 'activo']);

    $clienteB = Cliente::factory()->create(['asesor_id' => $asesorB->id]);
    ClienteScoring::create([
        'cliente_id' => $clienteB->id,
        'score'      => 90,
        'grado'      => 'A',
        'vigente'    => true,
    ]);

    // Asesor A has no clientes with scoring
    $this->actingAs($this->user);

    $component = Livewire::test(AsesorDashboard::class);

    // Asesor A's gradoData should be empty (no scoring for their clientes)
    expect($component->get('gradoData'))->toBeEmpty();
});
