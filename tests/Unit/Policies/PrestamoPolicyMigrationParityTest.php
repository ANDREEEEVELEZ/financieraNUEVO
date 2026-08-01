<?php

declare(strict_types=1);

use App\Contracts\CacheServiceInterface;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\User;
use App\Policies\PrestamoPolicy;

/**
 * Slice 5d (design D6, spec Requirement 4.1 / Scenario 4.1.b): PrestamoResource
 * previously gated the Asesor-filter visibility, the `reenviar` action, and the
 * static `canEdit`/`canView`/`canCreate`/`canDelete` overrides with inline
 * `request()->user()->hasRole(...)`/`hasAnyRole(...)` checks (Bucket A per
 * D6's triage). This migration:
 *
 *  - adds two new Policy methods (`verFiltroAsesor`, `reenviar`) for the two
 *    call sites that had no existing Policy equivalent;
 *  - replaces `PrestamoPolicy::view/create/update/delete`'s PRE-EXISTING
 *    bodies (plain Spatie-permission checks, e.g. `$user->can('update_prestamo')`)
 *    with the verbatim body of `PrestamoResource::canView/canCreate/canEdit/
 *    canDelete()`, which were the actual authorization source for the panel —
 *    the pre-existing Spatie-permission bodies were provably unreachable
 *    (grepped: zero callers anywhere in `app/` ever call
 *    `$user->can('update_prestamo')`/`'view_prestamo'`/`'create_prestamo'`/
 *    `'delete_prestamo'` directly, and the Resource's own canEdit/canView/
 *    canCreate/canDelete overrides always short-circuited before Filament
 *    could fall back to the Policy). This is a verified drift resolution,
 *    not a silent pick between two active authorization paths — see the
 *    apply-progress note for PR9 for the full verification trail.
 *
 * `aprobar`/`firmar`/`desembolsar`/`rechazar` were found ALREADY migrated to
 * `auth()->user()?->can(...)` calls prior to this PR (no drift — verified
 * their Policy bodies already matched every inline closure they replaced) —
 * not touched here.
 *
 * Deliberately DB-free (see PR2-PR9 environment constraint: the remote test
 * DB is unreachable in this sandbox): `hasRole`/`hasAnyRole` are stubbed via
 * a Mockery partial mock of `User`, matching the convention established in
 * `PagoPolicyMigrationParityTest`. The Asesor-ownership branches of `view`/
 * `update` (real `CacheServiceInterface::getAsesorByUserId()` + `$prestamo->
 * grupo` relation) ARE exercised here without touching the database: the
 * container binding is swapped for a stub, and `grupo` is set via
 * `setRelation()` (in-memory, no query) rather than a real Eloquent load —
 * this is safe because `setRelation()` never issues SQL.
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

function sameRoleSetPrestamo(array $expected)
{
    return Mockery::on(fn ($actual) => is_array($actual) && count(array_diff($expected, $actual)) === 0 && count(array_diff($actual, $expected)) === 0);
}

// ─── verFiltroAsesor (PrestamoResource.php:815) ───

it('verFiltroAsesor preserves the role-axis outcome of the original hasRole closure', function (string $role, bool $expected) {
    $policy = new PrestamoPolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasRole')->with('Asesor')->andReturn($role === 'Asesor');

    expect($policy->verFiltroAsesor($user))->toBe($expected);
})->with([
    'Asesor -> hidden' => ['Asesor', false],
    'Jefe de creditos -> visible' => ['Jefe de creditos', true],
    'super_admin -> visible' => ['super_admin', true],
]);

// ─── reenviar (PrestamoResource.php:1098-1099) ───

dataset('reenviar_role_matrix', [
    'Asesor -> allowed' => ['Asesor', true],
    'super_admin -> allowed' => ['super_admin', true],
    'Jefe de creditos -> denied' => ['Jefe de creditos', false],
    'Jefe de operaciones -> denied' => ['Jefe de operaciones', false],
]);

it('reenviar preserves the role-axis outcome for a Reformulado préstamo', function (string $role, bool $expected) {
    $policy = new PrestamoPolicy();
    $prestamo = new Prestamo(['estado' => Prestamo::ESTADO_REFORMULADO]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetPrestamo(['Asesor', 'super_admin']))
        ->andReturn(in_array($role, ['Asesor', 'super_admin'], true));

    expect($policy->reenviar($user, $prestamo))->toBe($expected);
})->with('reenviar_role_matrix');

it('reenviar denies every role when the préstamo is not in estado Reformulado (state axis, folded into the Policy)', function () {
    $policy = new PrestamoPolicy();
    $prestamo = new Prestamo(['estado' => Prestamo::ESTADO_PENDIENTE]);

    $user = Mockery::mock(User::class)->makePartial();
    // hasAnyRole must never even be consulted once the state check short-circuits.
    $user->shouldNotReceive('hasAnyRole');

    expect($policy->reenviar($user, $prestamo))->toBeFalse();
});

// ─── view (PrestamoResource::canView, drift-resolved) ───

it('view allows privileged roles unconditionally', function (string $role) {
    $policy = new PrestamoPolicy();
    $prestamo = new Prestamo();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasRole')->with('Asesor')->andReturn(false);
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetPrestamo(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
        ->andReturn(true);

    expect($policy->view($user, $prestamo))->toBeTrue();
})->with(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);

it('view denies a role with neither the privileged set nor Asesor', function () {
    $policy = new PrestamoPolicy();
    $prestamo = new Prestamo();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasRole')->with('Asesor')->andReturn(false);
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetPrestamo(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
        ->andReturn(false);

    expect($policy->view($user, $prestamo))->toBeFalse();
});

it('view scopes Asesor to préstamos of their own grupo (ownership branch, DB-free via setRelation)', function (bool $ownsGroup, bool $expected) {
    $policy = new PrestamoPolicy();

    $grupo = new Grupo(['asesor_id' => $ownsGroup ? 42 : 99]);
    $prestamo = new Prestamo();
    $prestamo->setRelation('grupo', $grupo);

    $asesorStub = (object) ['id' => 42];
    $this->app->bind(CacheServiceInterface::class, fn () => new class ($asesorStub) {
        public function __construct(private object $asesor) {}
        public function getAsesorByUserId(int $userId) { return $this->asesor; }
    });

    $user = Mockery::mock(User::class)->makePartial();
    $user->id = 7;
    $user->shouldReceive('hasRole')->with('Asesor')->andReturn(true);

    expect($policy->view($user, $prestamo))->toBe($expected);
})->with([
    'owns the grupo -> allowed' => [true, true],
    'does not own the grupo -> denied' => [false, false],
]);

// ─── create (PrestamoResource::canCreate, drift-resolved) ───

it('create preserves the role-axis outcome of the original hasAnyRole closure', function (string $role, bool $expected) {
    $policy = new PrestamoPolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetPrestamo(['super_admin', 'Jefe de operaciones', 'Jefe de creditos', 'Asesor']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones', 'Jefe de creditos', 'Asesor'], true));

    expect($policy->create($user))->toBe($expected);
})->with([
    'super_admin -> allowed' => ['super_admin', true],
    'Jefe de operaciones -> allowed' => ['Jefe de operaciones', true],
    'Jefe de creditos -> allowed' => ['Jefe de creditos', true],
    'Asesor -> allowed' => ['Asesor', true],
]);

// ─── update (PrestamoResource::canEdit, drift-resolved) ───

it('update denies a retanqueo préstamo regardless of role', function () {
    $policy = new PrestamoPolicy();
    $prestamo = new Prestamo(['estado' => Prestamo::ESTADO_PENDIENTE, 'es_retanqueo' => true]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldNotReceive('hasRole');
    $user->shouldNotReceive('hasAnyRole');

    expect($policy->update($user, $prestamo))->toBeFalse();
});

it('update denies a préstamo not in estado Pendiente regardless of role', function () {
    $policy = new PrestamoPolicy();
    $prestamo = new Prestamo(['estado' => Prestamo::ESTADO_APROBADO, 'es_retanqueo' => false]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldNotReceive('hasRole');
    $user->shouldNotReceive('hasAnyRole');

    expect($policy->update($user, $prestamo))->toBeFalse();
});

it('update allows privileged roles for a Pendiente, non-retanqueo préstamo', function (string $role) {
    $policy = new PrestamoPolicy();
    $prestamo = new Prestamo(['estado' => Prestamo::ESTADO_PENDIENTE, 'es_retanqueo' => false]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasRole')->with('Asesor')->andReturn(false);
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetPrestamo(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
        ->andReturn(true);

    expect($policy->update($user, $prestamo))->toBeTrue();
})->with(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);

// ─── delete (PrestamoResource::canDelete, drift-resolved) ───

it('delete denies a retanqueo préstamo regardless of role', function () {
    $policy = new PrestamoPolicy();
    $prestamo = new Prestamo(['es_retanqueo' => true]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldNotReceive('hasRole');

    expect($policy->delete($user, $prestamo))->toBeFalse();
});

it('delete preserves the role-axis outcome of the original hasRole closure for a non-retanqueo préstamo', function (string $role, bool $expected) {
    $policy = new PrestamoPolicy();
    $prestamo = new Prestamo(['es_retanqueo' => false]);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasRole')->with('super_admin')->andReturn($role === 'super_admin');

    expect($policy->delete($user, $prestamo))->toBe($expected);
})->with([
    'super_admin -> allowed' => ['super_admin', true],
    'Jefe de creditos -> denied' => ['Jefe de creditos', false],
    'Asesor -> denied' => ['Asesor', false],
]);
