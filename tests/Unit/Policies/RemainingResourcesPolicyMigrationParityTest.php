<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\ClientePolicy;
use App\Policies\CuotasGrupalesPolicy;
use App\Policies\EgresoPolicy;
use App\Policies\IngresoPolicy;

/**
 * Slice 5g (design D6, spec Requirement 4.1 / Scenario 4.1.b) — the LAST
 * call-site migration PR. Scope re-derived at apply time via a fresh
 * project-wide `rg -n "hasRole|hasAnyRole" app/Filament --type=php -l`,
 * subtracting the files already migrated by PR8-11 (PagoResource+Pages,
 * PrestamoResource+Pages, RetanqueoResource+Pages, GrupoResource+Pages).
 * The actual remaining scope is 13 files / 28 raw hits — wider than design's
 * ~17-hit estimate, but classified as follows:
 *
 *  - Bucket A (migrated here, 11 raw hits / 8 distinct sites):
 *    `ClienteResource.php` (asesor_id field required+visible = 1 site/2
 *    hits, table asesor SelectFilter visible = 1 site, row Action
 *    `trasladar_cliente` visible = 1 site), `ListClientes.php` (header
 *    Action `trasladar_clientes_masivo` visible = 1 site),
 *    `CuotasResource.php` + `CuotasVigentesWidget.php` (asesor-column
 *    visible, SAME closure on the SAME model `CuotasGrupales`, migrated to
 *    ONE shared `CuotasGrupalesPolicy::verColumnaAsesor` — see that class's
 *    docblock for why this corrects a stale Bucket-C note from PR5),
 *    `EgresosResource.php` + `IngresosResource.php` (`canAccess()` +
 *    `shouldRegisterNavigation()` static overrides, a CONFIRMED drift case:
 *    both bypass `EgresoPolicy`/`IngresoPolicy::viewAny()` entirely, and
 *    those Policies' pre-existing Spatie-permission bodies were not just
 *    uncalled but NEVER SEEDED in `PermissionSeeder` — 100% dead code. See
 *    `EgresoPolicy`'s docblock for the full drift-resolution rationale).
 *  - Bucket B (data scoping, untouched, 6 raw hits): `ClienteResource.php`
 *    `getEloquentQuery()`, `CuotasResource.php`/`CuotasVigentesWidget.php`
 *    `getTableQuery()`/`table()` asesor filtering, `UserResource.php`
 *    `getEloquentQuery()`, `ClienteStatsWidget.php`'s stat-scoping,
 *    `AsesorPage.php`'s `resolveAsesor()`.
 *  - Business logic, not authorization (untouched, 3 raw hits):
 *    `CreateCliente.php`/`EditCliente.php`'s asesor auto-assign in
 *    `mutateFormDataBeforeCreate`/`mutateFormDataBeforeSave` (same pattern
 *    as PR11's `CreateGrupo.php`/`EditGrupo.php` precedent).
 *  - Flagged, non-matching syntax (NOT migrated, same "flag don't guess"
 *    treatment as PR9-PR11's precedent, 8 raw hits): `ClienteResource.php:304`
 *    (raw `if` gating whether the asesor column is appended to `$columns`,
 *    same shape as PR11's flagged `GrupoResource.php:325`);
 *    `AsesorDashboard.php` (4 hits: `isOperacionesLocked` plain property
 *    assignment, `resolveTabLayout()`'s role-based tab-order selection,
 *    `resolveAsesor()`); `Dashboard.php` (3 hits: `getWidgets()`'s raw `if`s
 *    selecting an entirely different widget SET per role — not a single
 *    boolean gate for one element, structurally further from Bucket A than
 *    even the flagged array-append precedents).
 *
 * Deliberately DB-free (standing environment constraint since PR2 — remote
 * test DB unreachable in this sandbox): `hasRole`/`hasAnyRole` are stubbed
 * via a Mockery partial mock of `User`, matching the convention established
 * in `PagoPolicyMigrationParityTest`/`PrestamoPolicyMigrationParityTest`/
 * `RetanqueoPolicyMigrationParityTest`/`GrupoPolicyMigrationParityTest`.
 * `Cliente`, `CuotasGrupales`, `Egreso`, `Ingreso` all use `$fillable` (not
 * `$guarded`) — confirmed via grep before writing fixtures — so the
 * guarded-model mass-assignment DB-schema-check hang (PR10's root-cause
 * finding) does not apply to any model touched by this PR (none of these
 * tests need a model instance at all, since every migrated method here is
 * a `viewAny`-shape or a bare role check with no per-record state axis).
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

function sameRoleSetRemaining(array $expected)
{
    return Mockery::on(fn ($actual) => is_array($actual) && count(array_diff($expected, $actual)) === 0 && count(array_diff($actual, $expected)) === 0);
}

// ─── ClientePolicy::verCampoAsesor (ClienteResource.php form asesor_id required()+visible()) ───

it('verCampoAsesor preserves the role-axis outcome of the original hasAnyRole closure', function (string $role, bool $expected) {
    $policy = new ClientePolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetRemaining(['super_admin', 'Jefe de operaciones']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones'], true));

    expect($policy->verCampoAsesor($user))->toBe($expected);
})->with([
    'super_admin -> visible' => ['super_admin', true],
    'Jefe de operaciones -> visible' => ['Jefe de operaciones', true],
    'Jefe de creditos -> hidden' => ['Jefe de creditos', false],
    'Asesor -> hidden' => ['Asesor', false],
]);

// ─── ClientePolicy::verFiltroAsesor (ClienteResource.php table asesor SelectFilter visible()) ───

it('verFiltroAsesor preserves the role-axis outcome of the original hasAnyRole closure', function (string $role, bool $expected) {
    $policy = new ClientePolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetRemaining(['super_admin', 'Jefe de operaciones']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones'], true));

    expect($policy->verFiltroAsesor($user))->toBe($expected);
})->with([
    'super_admin -> visible' => ['super_admin', true],
    'Jefe de operaciones -> visible' => ['Jefe de operaciones', true],
    'Jefe de creditos -> hidden' => ['Jefe de creditos', false],
    'Asesor -> hidden' => ['Asesor', false],
]);

// ─── ClientePolicy::trasladarCliente (ClienteResource.php row Action visible()) ───

it('trasladarCliente preserves the role-axis outcome of the original hasAnyRole closure', function (string $role, bool $expected) {
    $policy = new ClientePolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetRemaining(['super_admin', 'Jefe de operaciones']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones'], true));

    expect($policy->trasladarCliente($user))->toBe($expected);
})->with([
    'super_admin -> visible' => ['super_admin', true],
    'Jefe de operaciones -> visible' => ['Jefe de operaciones', true],
    'Jefe de creditos -> hidden' => ['Jefe de creditos', false],
    'Asesor -> hidden' => ['Asesor', false],
]);

// ─── ClientePolicy::trasladarClientesMasivo (ListClientes.php header Action visible(), wider role set) ───

it('trasladarClientesMasivo preserves the role-axis outcome of the original hasAnyRole closure', function (string $role, bool $expected) {
    $policy = new ClientePolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetRemaining(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones', 'Jefe de creditos'], true));

    expect($policy->trasladarClientesMasivo($user))->toBe($expected);
})->with([
    'super_admin -> visible' => ['super_admin', true],
    'Jefe de operaciones -> visible' => ['Jefe de operaciones', true],
    'Jefe de creditos -> visible' => ['Jefe de creditos', true],
    'Asesor -> hidden' => ['Asesor', false],
]);

// ─── CuotasGrupalesPolicy::verColumnaAsesor (CuotasResource.php + CuotasVigentesWidget.php, shared method) ───

it('verColumnaAsesor preserves the role-axis outcome of the original !hasRole(Asesor) closure', function (string $role, bool $expected) {
    $policy = new CuotasGrupalesPolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasRole')->with('Asesor')->andReturn($role === 'Asesor');

    expect($policy->verColumnaAsesor($user))->toBe($expected);
})->with([
    'Asesor -> hidden' => ['Asesor', false],
    'Jefe de creditos -> visible' => ['Jefe de creditos', true],
    'Jefe de operaciones -> visible' => ['Jefe de operaciones', true],
    'super_admin -> visible' => ['super_admin', true],
]);

// ─── EgresoPolicy::viewAny (EgresosResource::canAccess/shouldRegisterNavigation, drift-resolved) ───

it('EgresoPolicy viewAny preserves the role-axis outcome of the original hasRole closure', function (string $role, bool $expected) {
    $policy = new EgresoPolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasRole')
        ->with(['super_admin', 'Jefe de operaciones'])
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones'], true));

    expect($policy->viewAny($user))->toBe($expected);
})->with([
    'super_admin -> allowed' => ['super_admin', true],
    'Jefe de operaciones -> allowed' => ['Jefe de operaciones', true],
    'Jefe de creditos -> denied' => ['Jefe de creditos', false],
    'Asesor -> denied' => ['Asesor', false],
]);

// ─── IngresoPolicy::viewAny (IngresosResource::canAccess/shouldRegisterNavigation, drift-resolved) ───

it('IngresoPolicy viewAny preserves the role-axis outcome of the original hasRole closure', function (string $role, bool $expected) {
    $policy = new IngresoPolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasRole')
        ->with(['super_admin', 'Jefe de operaciones'])
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones'], true));

    expect($policy->viewAny($user))->toBe($expected);
})->with([
    'super_admin -> allowed' => ['super_admin', true],
    'Jefe de operaciones -> allowed' => ['Jefe de operaciones', true],
    'Jefe de creditos -> denied' => ['Jefe de creditos', false],
    'Asesor -> denied' => ['Asesor', false],
]);
