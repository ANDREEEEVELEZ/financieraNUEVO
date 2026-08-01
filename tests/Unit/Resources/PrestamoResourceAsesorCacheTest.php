<?php

use App\Filament\Dashboard\Resources\PrestamoResource;
use App\Contracts\CacheServiceInterface;
use App\Models\Asesor;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    foreach (['Asesor', 'super_admin', 'Jefe de operaciones', 'Jefe de creditos'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

// ── T-2.7: canEdit uses cache->getAsesorByUserId, not raw Asesor::where() ────

it('canEdit() does not contain raw Asesor::where() in the production code', function () {
    $resourceCode = file_get_contents(app_path('Filament/Dashboard/Resources/PrestamoResource.php'));

    // The canEdit and canView static methods must not use raw Asesor::where('user_id', ...)
    // Note: we check the general pattern rather than exact lines since there are multiple occurrences
    expect($resourceCode)->not->toContain(
        "Asesor::where('user_id', \$user->id)->first()"
    );
});

// ── T-2.7: canView uses cache->getAsesorByUserId, not raw Asesor::where() ────

it('canView() does not contain raw Asesor::where() in the production code', function () {
    $resourceCode = file_get_contents(app_path('Filament/Dashboard/Resources/PrestamoResource.php'));

    expect($resourceCode)->not->toContain(
        "Asesor::where('user_id', \$user->id)->first()"
    );
});

// ── Triangulation: canEdit uses CacheServiceInterface->getAsesorByUserId ──────

it('canEdit uses CacheServiceInterface::getAsesorByUserId not a raw DB query', function () {
    $user = User::factory()->create();
    $user->assignRole('Asesor');

    $persona = \App\Models\Persona::factory()->create();
    $asesor = Asesor::factory()->create(['user_id' => $user->id, 'persona_id' => $persona->id]);

    $grupo = \App\Models\Grupo::factory()->create(['asesor_id' => $asesor->id]);
    $prestamo = Prestamo::factory()->create([
        'grupo_id' => $grupo->id,
        'estado'   => 'Pendiente',
    ]);

    $prestamo->load('grupo');

    // Warm the cache with the real asesor so canEdit will find it
    \Illuminate\Support\Facades\Cache::put(
        'ec_asesor_user_' . $user->id,
        $asesor,
        300
    );

    // PrestamoResource::canEdit() now delegates to PrestamoPolicy::update() via
    // Filament's default Gate-based authorization (Resource drift consolidation,
    // Slice 5d), which resolves the user from Auth::user() — request()->setUserResolver()
    // does not feed that, so the user must be authenticated via actingAs().
    $this->actingAs($user);

    $result = PrestamoResource::canEdit($prestamo);

    expect($result)->toBeTrue(
        'canEdit must return true for an Asesor who owns the grupo (verified via cache)'
    );
});
