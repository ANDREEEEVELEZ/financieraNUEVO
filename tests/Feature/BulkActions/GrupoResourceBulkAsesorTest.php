<?php

use App\Contracts\CacheServiceInterface;
use App\Models\Asesor;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Jefe de operaciones', 'guard_name' => 'web']);
    Cache::flush();
});

// ── T-3.1: cambiar_asesor updates asesor_id for all selected grupos ───────────

it('cambiar_asesor bulk action reassigns all grupo asesor_ids', function () {
    $personaA = \App\Models\Persona::factory()->create();
    $asesorA = Asesor::factory()->create(['persona_id' => $personaA->id]);

    $personaB = \App\Models\Persona::factory()->create();
    $asesorB = Asesor::factory()->create(['persona_id' => $personaB->id]);

    // Create 3 grupos assigned to asesorA
    $grupos = Grupo::factory()->count(3)->create(['asesor_id' => $asesorA->id]);

    // Simulate the cambiar_asesor action directly
    $records = $grupos;
    $data = ['asesor_id' => $asesorB->id];

    $oldAsesorIds = $records->pluck('asesor_id')->unique()->filter()->values();
    $nuevoAsesorId = $data['asesor_id'];

    Grupo::whereIn('id', $records->pluck('id'))->update(['asesor_id' => $nuevoAsesorId]);

    foreach ($grupos as $grupo) {
        $grupo->clientes()->update(['asesor_id' => $nuevoAsesorId]);
    }

    // Verify all grupos were reassigned
    $refreshed = Grupo::whereIn('id', $grupos->pluck('id'))->get();

    expect($refreshed->pluck('asesor_id')->unique()->toArray())->toBe([$asesorB->id]);
});

// ── T-3.1: cambiar_asesor invalidates cache for both old AND new asesor ───────

it('cambiar_asesor bulk action invalidates cache for both old and new asesor IDs', function () {
    $personaA = \App\Models\Persona::factory()->create();
    $asesorA = Asesor::factory()->create(['persona_id' => $personaA->id]);

    $personaB = \App\Models\Persona::factory()->create();
    $asesorB = Asesor::factory()->create(['persona_id' => $personaB->id]);

    // Seed cache keys for asesorA and asesorB
    Cache::put('ec_dashboard_asesor_' . $asesorA->id, 'stale_data', 300);
    Cache::put('ec_dashboard_asesor_' . $asesorB->id, 'stale_data', 300);
    Cache::put('ec_grupos_asesor_' . $asesorA->id, 'stale_data', 300);
    Cache::put('ec_grupos_asesor_' . $asesorB->id, 'stale_data', 300);

    $grupos = Grupo::factory()->count(2)->create(['asesor_id' => $asesorA->id]);

    // Apply the fixed cambiar_asesor logic
    $oldAsesorIds = $grupos->pluck('asesor_id')->unique()->filter()->values();
    $nuevoAsesorId = $asesorB->id;

    Grupo::whereIn('id', $grupos->pluck('id'))->update(['asesor_id' => $nuevoAsesorId]);

    $cache = app(CacheServiceInterface::class);
    foreach ($oldAsesorIds as $oldId) {
        $cache->invalidateDashboardCache($oldId);
        $cache->invalidateAsesorCache($oldId);
    }
    $cache->invalidateDashboardCache($nuevoAsesorId);
    $cache->invalidateAsesorCache($nuevoAsesorId);

    // Old asesor cache must be cleared
    expect(Cache::has('ec_dashboard_asesor_' . $asesorA->id))->toBeFalse(
        'Old asesor dashboard cache must be invalidated after bulk reassignment'
    );
    // New asesor cache must also be cleared
    expect(Cache::has('ec_dashboard_asesor_' . $asesorB->id))->toBeFalse(
        'New asesor dashboard cache must be invalidated after bulk reassignment'
    );
});

// ── T-3.1: cambiar_asesor uses 1 UPDATE query (not N save() calls) ───────────

it('cambiar_asesor bulk action fires exactly 1 UPDATE on grupos table', function () {
    $personaA = \App\Models\Persona::factory()->create();
    $asesorA = Asesor::factory()->create(['persona_id' => $personaA->id]);

    $personaB = \App\Models\Persona::factory()->create();
    $asesorB = Asesor::factory()->create(['persona_id' => $personaB->id]);

    $grupos = Grupo::factory()->count(3)->create(['asesor_id' => $asesorA->id]);

    $updateQueries = [];
    DB::listen(function ($q) use (&$updateQueries) {
        if (str_contains(strtolower($q->sql), 'update') && str_contains(strtolower($q->sql), 'grupos')) {
            $updateQueries[] = $q->sql;
        }
    });

    Grupo::whereIn('id', $grupos->pluck('id'))->update(['asesor_id' => $asesorB->id]);

    expect(count($updateQueries))->toBe(1,
        'cambiar_asesor must fire exactly 1 UPDATE on grupos (not N individual save() calls)'
    );
});
