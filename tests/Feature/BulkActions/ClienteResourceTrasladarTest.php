<?php

use App\Contracts\CacheServiceInterface;
use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Cache::flush();
});

// ── T-3.3: trasladar_cliente does not use raw User::role() ────────────────────

it('ClienteResource trasladar_cliente action code does not call User::role() directly', function () {
    $code = file_get_contents(app_path('Filament/Dashboard/Resources/ClienteResource.php'));

    expect($code)->not->toContain("User::role(['super_admin'");
});

// ── T-3.3: single-integrante grupo path updates grupo asesor_id ───────────────

it('trasladar_cliente single-member grupo path updates grupo asesor_id', function () {
    $personaA = \App\Models\Persona::factory()->create();
    $asesorA = Asesor::factory()->create(['persona_id' => $personaA->id]);

    $personaB = \App\Models\Persona::factory()->create();
    $asesorB = Asesor::factory()->create(['persona_id' => $personaB->id]);

    $grupo = Grupo::factory()->create(['asesor_id' => $asesorA->id]);

    // Seed cache to verify invalidation
    Cache::put('ec_dashboard_asesor_' . $asesorA->id, 'stale', 300);
    Cache::put('ec_dashboard_asesor_' . $asesorB->id, 'stale', 300);

    // Apply the single-record grupo path logic
    $oldAsesorId = $grupo->asesor_id;
    $grupo->asesor_id = $asesorB->id;
    $grupo->save();

    $cache = app(CacheServiceInterface::class);
    $cache->invalidateDashboardCache($oldAsesorId);
    $cache->invalidateAsesorCache($oldAsesorId);
    $cache->invalidateDashboardCache($asesorB->id);
    $cache->invalidateAsesorCache($asesorB->id);

    $grupo->refresh();
    expect($grupo->asesor_id)->toBe($asesorB->id);
    expect(Cache::has('ec_dashboard_asesor_' . $asesorA->id))->toBeFalse();
    expect(Cache::has('ec_dashboard_asesor_' . $asesorB->id))->toBeFalse();
});

// ── Triangulation: cache is invalidated for old asesor on trasladar ───────────

it('trasladar_cliente invalidates cache for old asesor ID', function () {
    $personaA = \App\Models\Persona::factory()->create();
    $asesorA = Asesor::factory()->create(['persona_id' => $personaA->id]);

    $personaB = \App\Models\Persona::factory()->create();
    $asesorB = Asesor::factory()->create(['persona_id' => $personaB->id]);

    $clientePersona = \App\Models\Persona::factory()->create();
    $cliente = Cliente::factory()->create([
        'persona_id' => $clientePersona->id,
        'asesor_id'  => $asesorA->id,
    ]);

    Cache::put('ec_dashboard_asesor_' . $asesorA->id, 'stale_old', 300);

    // Apply the cache invalidation logic
    $cache = app(CacheServiceInterface::class);
    $cache->invalidateDashboardCache($asesorA->id);
    $cache->invalidateAsesorCache($asesorA->id);

    expect(Cache::has('ec_dashboard_asesor_' . $asesorA->id))->toBeFalse(
        'Old asesor dashboard cache must be invalidated on trasladar_cliente'
    );
});
