<?php

declare(strict_types=1);

use App\Models\Pago;
use App\Models\User;
use App\Policies\PagoPolicy;

/**
 * Slice 5c (design D6, spec Requirement 4.1 / Scenario 4.1.b): PagoResource,
 * EditPago, and GrupoDetallePagos previously gated the aprobar/rechazar/
 * revertir/aprobarMasivo/crearParaGrupo/verEnGrupoDetalle actions with inline
 * `request()->user()?->hasAnyRole([...])` / `Auth::user()->hasRole(...)`
 * closures (Bucket A per D6's triage). This migration replaces every such
 * inline check with `$user->can('<ability>', $record)`, resolved through
 * `PagoPolicy`. The state-check portion of each original closure (e.g.
 * `estado_pago === 'pendiente'`) is intentionally NOT folded into the Policy
 * method — it stays inline at each Filament call site (same convention as
 * `PrestamoPolicy::aprobar/firmar/desembolsar`, whose docblocks say "state
 * validity is checked separately by the caller"), because the exact state
 * value being checked differs per call site (aprobar/rechazar check
 * 'pendiente', revertir checks 'aprobado') and a single Policy method cannot
 * encode two different literal state values for two different call sites
 * without losing the 1:1 mapping to the original closures.
 *
 * These tests assert ROLE-axis parity for every migrated ability (the STATE
 * axis is an unchanged, visually-diffable one-line condition at each call
 * site, confirmed separately by the Scenario 4.1.a grep sweep) — for every
 * role in the role matrix, the Policy method must return the exact same
 * boolean the original inline `hasAnyRole`/`hasRole` closure returned. Role
 * SETS are matched by content, not literal array order (`hasAnyRole` is
 * order-independent by definition — pinning the test to a literal order
 * would over-fit to an implementation detail).
 *
 * Deliberately DB-free (see PR2-PR8 environment constraint: the remote test
 * DB is unreachable in this sandbox): `hasAnyRole`/`hasRole` are stubbed via
 * a Mockery partial mock of `User`, matching the convention established in
 * `FinancialModelsPolicyTest`/`LifecycleModelsPolicyTest`/
 * `CatalogModelsPolicyTest`. `verEnGrupoDetalle`'s Asesor-ownership branch
 * (`\App\Models\Asesor::where('user_id', ...)->first()`) is a real Eloquent
 * query the ORIGINAL closure already had — this was never testable without a
 * database, migration or not — so only the DB-independent short-circuit
 * branches (super_admin/Jefe de operaciones/Jefe de creditos → always true)
 * are exercised here; the Asesor-ownership branch is a standing gap carried
 * over unchanged from the pre-migration closure, not a new one introduced by
 * this PR.
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

function sameRoleSet(array $expected)
{
    return Mockery::on(fn ($actual) => is_array($actual) && count(array_diff($expected, $actual)) === 0 && count(array_diff($actual, $expected)) === 0);
}

// ─── aprobar (PagoResource.php:1013, EditPago.php:165, GrupoDetallePagos.php:240,891) ───

dataset('aprobar_rechazar_role_matrix', [
    'super_admin -> allowed' => ['super_admin', true],
    'Jefe de operaciones -> allowed' => ['Jefe de operaciones', true],
    'Jefe de creditos -> denied' => ['Jefe de creditos', false],
    'Asesor -> denied' => ['Asesor', false],
]);

it('aprobar preserves the role-axis outcome of the original hasAnyRole closure', function (string $role, bool $expected) {
    $policy = new PagoPolicy();
    $pago = new Pago();

    // Simulate hasAnyRole(['super_admin', 'Jefe de operaciones']) for a user
    // holding exactly $role — same semantics as the pre-migration closures.
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSet(['super_admin', 'Jefe de operaciones']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones'], true));

    expect($policy->aprobar($user, $pago))->toBe($expected);
})->with('aprobar_rechazar_role_matrix');

it('rechazar preserves the role-axis outcome of the original hasAnyRole closure', function (string $role, bool $expected) {
    $policy = new PagoPolicy();
    $pago = new Pago();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSet(['super_admin', 'Jefe de operaciones']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones'], true));

    expect($policy->rechazar($user, $pago))->toBe($expected);
})->with('aprobar_rechazar_role_matrix');

it('revertir preserves the role-axis outcome of the original hasAnyRole closure', function (string $role, bool $expected) {
    $policy = new PagoPolicy();
    $pago = new Pago();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSet(['super_admin', 'Jefe de operaciones']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones'], true));

    expect($policy->revertir($user, $pago))->toBe($expected);
})->with('aprobar_rechazar_role_matrix');

it('aprobarMasivo preserves the role-axis outcome of the original bulk-action closure', function (string $role, bool $expected) {
    $policy = new PagoPolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSet(['super_admin', 'Jefe de operaciones']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones'], true));

    expect($policy->aprobarMasivo($user))->toBe($expected);
})->with('aprobar_rechazar_role_matrix');

it('crearParaGrupo preserves the role-axis outcome of the original hasRole closure', function () {
    $policy = new PagoPolicy();

    $asesor = Mockery::mock(User::class)->makePartial();
    $asesor->shouldReceive('hasRole')->with('Asesor')->andReturn(true);
    expect($policy->crearParaGrupo($asesor))->toBeTrue();

    $superAdmin = Mockery::mock(User::class)->makePartial();
    $superAdmin->shouldReceive('hasRole')->with('Asesor')->andReturn(false);
    expect($policy->crearParaGrupo($superAdmin))->toBeFalse();
});

// ─── verEnGrupoDetalle (GrupoDetallePagos.php:828-845) — DB-independent branches only ───

it('verEnGrupoDetalle allows super_admin, Jefe de operaciones, and Jefe de creditos unconditionally', function (string $role) {
    $policy = new PagoPolicy();
    $pago = new Pago();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSet(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
        ->andReturn(true);

    expect($policy->verEnGrupoDetalle($user, $pago))->toBeTrue();
})->with(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']);

it('verEnGrupoDetalle denies a role with neither the privileged set nor Asesor', function () {
    $policy = new PagoPolicy();
    $pago = new Pago();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSet(['super_admin', 'Jefe de operaciones', 'Jefe de creditos']))
        ->andReturn(false);
    $user->shouldReceive('hasRole')->with('Asesor')->andReturn(false);

    expect($policy->verEnGrupoDetalle($user, $pago))->toBeFalse();
});
