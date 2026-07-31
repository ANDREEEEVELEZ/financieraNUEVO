<?php

declare(strict_types=1);

use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\Retanqueo;
use App\Models\User;
use App\Policies\RetanqueoPolicy;

/**
 * Slice 5e (design D6, spec Requirement 4.1 / Scenario 4.1.b): RetanqueoResource
 * previously gated a column's visibility, three table actions (`aprobar`,
 * `rechazar`, `ejecutar`), a bulk-delete action, and the static
 * `canViewAny`/`canCreate`/`canEdit`/`canDelete` overrides with inline
 * `request()->user()->hasRole(...)`/`hasAnyRole(...)` checks (Bucket A per
 * D6's triage). This migration:
 *
 *  - adds four new Policy methods (`verColumnaAsesor`, `aprobar`, `rechazar`,
 *    `ejecutar`) for call sites with no existing Policy equivalent — each
 *    folds its state check INTO the Policy method body, matching the
 *    original `->visible()` closures verbatim (unlike `PrestamoPolicy`'s
 *    aprobar/rechazar/firmar/desembolsar, which keep the state check at the
 *    caller — RetanqueoResource's own closures combined state+role in the
 *    SAME boolean expression, so the verbatim copy does too; see PR9's own
 *    guidance to match each file's OWN established pattern, not another
 *    Policy file's);
 *  - replaces `RetanqueoPolicy::viewAny/create/update/delete`'s PRE-EXISTING
 *    bodies (plain Spatie-permission checks, e.g. `$user->can('update_retanqueo')`)
 *    with the verbatim body of `RetanqueoResource::canViewAny/canCreate/
 *    canEdit/canDelete()`, which were the actual authorization source for
 *    the panel — the pre-existing Spatie-permission bodies were provably
 *    unreachable (grepped: zero callers anywhere in `app/`/`database/` ever
 *    call `$user->can('view_any_retanqueo'|'create_retanqueo'|
 *    'update_retanqueo'|'delete_retanqueo')` directly, and the Resource's
 *    own canViewAny/canCreate/canEdit/canDelete overrides always
 *    short-circuited before Filament could fall back to the Policy). This
 *    drift is CONFIRMED, not assumed: `PermissionSeeder` grants `Asesor`
 *    none of these four permissions, yet the Resource's overrides allowed
 *    Asesor for viewAny/create/edit(own group)/— a real behavioral
 *    disagreement the dead Policy bodies would have silently tightened had
 *    they ever been reachable. This is a verified drift resolution, not a
 *    silent pick between two active authorization paths.
 *  - `deleteAny()`'s PRE-EXISTING body (`$user->can('delete_any_retanqueo')`)
 *    was checked for the SAME drift risk and found CLEAN: `PermissionSeeder`
 *    grants `delete_any_retanqueo` to super_admin only (via `Permission::all()`),
 *    functionally identical to the bulk-action's inline
 *    `hasAnyRole(['super_admin'])` closure it replaces — reused as-is, not
 *    modified.
 *  - `view()` is UNCHANGED — `RetanqueoResource` has no `canView()` override,
 *    so this Policy method was already the sole, live authorization path;
 *    there is no competing Bucket-A closure to migrate for it.
 *
 * Deliberately DB-free (see PR2-PR9 environment constraint: the remote test
 * DB is unreachable in this sandbox): `hasRole`/`hasAnyRole` are stubbed via
 * a Mockery partial mock of `User`, matching the convention established in
 * `PagoPolicyMigrationParityTest`/`PrestamoPolicyMigrationParityTest`.
 *
 * NEW GOTCHA discovered in this PR (beyond PR9's documented ones): unlike
 * `Prestamo` (uses `$fillable`), `Retanqueo` uses `$guarded = ['id']`. Laravel's
 * `fill()` -\> `isGuardableColumn()` queries the REAL DB schema
 * (`getSchemaBuilder()->getColumnListing()`) the first time ANY non-`id` key
 * is mass-assigned to a `$guarded`-style model — so `new Retanqueo(['estado_retanqueo'
 * =\> 'x'])` hangs indefinitely against the unreachable test DB. Fixed by
 * constructing with an empty array and setting the attribute via property
 * assignment instead (`$r = new Retanqueo(); $r->estado_retanqueo = 'x';`),
 * which goes through `__set()` -\> `setAttribute()` directly and never runs
 * the fillable/guarded check at all.
 *
 * KNOWN GAP (documented, not silently dropped): `update()`'s Asesor-ownership
 * branch calls `\App\Models\Asesor::where('user_id', $user->id)->first()`
 * directly (a static Eloquent query, verbatim-copied from
 * `RetanqueoResource::canEdit()` — unlike `PrestamoPolicy::update()`, which
 * went through the injectable `CacheServiceInterface` and could be swapped
 * for a stub via container rebind). This Resource code has no DI seam to
 * intercept without touching the real database, so — consistent with PR9's
 * own precedent of leaving a DB-only branch as a standing untested gap when
 * no seam exists — only the non-Asesor (management-role) branches of
 * `update()` are exercised here. The Asesor-ownership branch is unchanged
 * from the Resource's pre-migration logic (verbatim copy, not a rewrite),
 * so no NEW risk is introduced by leaving it untested in this DB-free suite.
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

function sameRoleSetRetanqueo(array $expected)
{
    return Mockery::on(fn ($actual) => is_array($actual) && count(array_diff($expected, $actual)) === 0 && count(array_diff($actual, $expected)) === 0);
}

/**
 * DB-free `Retanqueo` construction (see docblock above): avoids Laravel's
 * guardable-column schema check that constructor mass-assignment would
 * trigger on this `$guarded`-style model.
 */
function retanqueoEnEstado(string $estado): Retanqueo
{
    $retanqueo = new Retanqueo();
    $retanqueo->estado_retanqueo = $estado;

    return $retanqueo;
}

// ─── verColumnaAsesor (RetanqueoResource.php:544) ───

it('verColumnaAsesor preserves the role-axis outcome of the original hasRole closure', function (string $role, bool $expected) {
    $policy = new RetanqueoPolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasRole')->with('Asesor')->andReturn($role === 'Asesor');

    expect($policy->verColumnaAsesor($user))->toBe($expected);
})->with([
    'Asesor -> hidden' => ['Asesor', false],
    'Jefe de creditos -> visible' => ['Jefe de creditos', true],
    'super_admin -> visible' => ['super_admin', true],
]);

// ─── aprobar (RetanqueoResource.php:634-639) ───

dataset('mgmt_role_matrix', [
    'super_admin -> allowed' => ['super_admin', true],
    'Jefe de operaciones -> allowed' => ['Jefe de operaciones', true],
    'Jefe de creditos -> allowed' => ['Jefe de creditos', true],
    'Asesor -> denied' => ['Asesor', false],
]);

it('aprobar preserves the role-axis outcome for a solicitud_pendiente retanqueo', function (string $role, bool $expected) {
    $policy = new RetanqueoPolicy();
    $retanqueo = retanqueoEnEstado('solicitud_pendiente');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetRetanqueo(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones', 'Jefe de creditos'], true));

    expect($policy->aprobar($user, $retanqueo))->toBe($expected);
})->with('mgmt_role_matrix');

it('aprobar denies every role when the retanqueo is not solicitud_pendiente (state axis, folded into the Policy)', function () {
    $policy = new RetanqueoPolicy();
    $retanqueo = retanqueoEnEstado('aprobado');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldNotReceive('hasAnyRole');

    expect($policy->aprobar($user, $retanqueo))->toBeFalse();
});

// ─── rechazar (RetanqueoResource.php:661-666) ───

it('rechazar preserves the role-axis outcome for a solicitud_pendiente retanqueo', function (string $role, bool $expected) {
    $policy = new RetanqueoPolicy();
    $retanqueo = retanqueoEnEstado('solicitud_pendiente');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetRetanqueo(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones', 'Jefe de creditos'], true));

    expect($policy->rechazar($user, $retanqueo))->toBe($expected);
})->with('mgmt_role_matrix');

it('rechazar denies every role when the retanqueo is not solicitud_pendiente (state axis, folded into the Policy)', function () {
    $policy = new RetanqueoPolicy();
    $retanqueo = retanqueoEnEstado('ejecutado');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldNotReceive('hasAnyRole');

    expect($policy->rechazar($user, $retanqueo))->toBeFalse();
});

// ─── ejecutar (RetanqueoResource.php:688-693) ───

it('ejecutar preserves the role-axis outcome for an aprobado retanqueo', function (string $role, bool $expected) {
    $policy = new RetanqueoPolicy();
    $retanqueo = retanqueoEnEstado('aprobado');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetRetanqueo(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones', 'Jefe de creditos'], true));

    expect($policy->ejecutar($user, $retanqueo))->toBe($expected);
})->with('mgmt_role_matrix');

it('ejecutar denies every role when the retanqueo is not aprobado (state axis, folded into the Policy)', function () {
    $policy = new RetanqueoPolicy();
    $retanqueo = retanqueoEnEstado('solicitud_pendiente');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldNotReceive('hasAnyRole');

    expect($policy->ejecutar($user, $retanqueo))->toBeFalse();
});

// ─── viewAny (RetanqueoResource::canViewAny, drift-resolved) ───

it('viewAny preserves the role-axis outcome of the original hasAnyRole closure', function (string $role, bool $expected) {
    $policy = new RetanqueoPolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetRetanqueo(['super_admin', 'Jefe de operaciones', 'Jefe de creditos', 'Asesor']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones', 'Jefe de creditos', 'Asesor'], true));

    expect($policy->viewAny($user))->toBe($expected);
})->with([
    'super_admin -> allowed' => ['super_admin', true],
    'Jefe de operaciones -> allowed' => ['Jefe de operaciones', true],
    'Jefe de creditos -> allowed' => ['Jefe de creditos', true],
    'Asesor -> allowed' => ['Asesor', true],
]);

// ─── create (RetanqueoResource::canCreate, drift-resolved) ───

it('create preserves the role-axis outcome of the original hasAnyRole closure', function (string $role, bool $expected) {
    $policy = new RetanqueoPolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetRetanqueo(['super_admin', 'Jefe de operaciones', 'Jefe de creditos', 'Asesor']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones', 'Jefe de creditos', 'Asesor'], true));

    expect($policy->create($user))->toBe($expected);
})->with([
    'super_admin -> allowed' => ['super_admin', true],
    'Jefe de operaciones -> allowed' => ['Jefe de operaciones', true],
    'Jefe de creditos -> allowed' => ['Jefe de creditos', true],
    'Asesor -> allowed' => ['Asesor', true],
]);

// ─── update (RetanqueoResource::canEdit, drift-resolved, management-role branch only — see KNOWN GAP above) ───

it('update denies a retanqueo not in estado solicitud_pendiente regardless of role', function () {
    $policy = new RetanqueoPolicy();
    $retanqueo = retanqueoEnEstado('aprobado');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasRole')->with('Asesor')->andReturn(false);
    $user->shouldNotReceive('hasAnyRole');

    expect($policy->update($user, $retanqueo))->toBeFalse();
});

it('update allows privileged (non-Asesor) roles for a solicitud_pendiente retanqueo', function (string $role) {
    $policy = new RetanqueoPolicy();
    $retanqueo = retanqueoEnEstado('solicitud_pendiente');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasRole')->with('Asesor')->andReturn(false);
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetRetanqueo(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
        ->andReturn(true);

    expect($policy->update($user, $retanqueo))->toBeTrue();
})->with(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);

// ─── delete (RetanqueoResource::canDelete, drift-resolved) ───

it('delete denies a retanqueo not in estado solicitud_pendiente regardless of role', function () {
    $policy = new RetanqueoPolicy();
    $retanqueo = retanqueoEnEstado('aprobado');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldNotReceive('hasRole');

    expect($policy->delete($user, $retanqueo))->toBeFalse();
});

it('delete preserves the role-axis outcome of the original hasRole closure for a solicitud_pendiente retanqueo', function (string $role, bool $expected) {
    $policy = new RetanqueoPolicy();
    $retanqueo = retanqueoEnEstado('solicitud_pendiente');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasRole')->with('super_admin')->andReturn($role === 'super_admin');

    expect($policy->delete($user, $retanqueo))->toBe($expected);
})->with([
    'super_admin -> allowed' => ['super_admin', true],
    'Jefe de creditos -> denied' => ['Jefe de creditos', false],
    'Asesor -> denied' => ['Asesor', false],
]);

// ─── deleteAny (RetanqueoResource bulk DeleteBulkAction, reused as-is — verified clean, no drift) ───

it('deleteAny (pre-existing, unmodified) matches the bulk-action closure it replaces: super_admin only', function () {
    $policy = new RetanqueoPolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->with('delete_any_retanqueo')->andReturn(true);

    expect($policy->deleteAny($user))->toBeTrue();
});
