<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\GrupoPolicy;

/**
 * Slice 5f (design D6, spec Requirement 4.1 / Scenario 4.1.b): GrupoResource
 * previously gated the asesor_id form field, the asesor SelectFilter, and the
 * `cambiar_asesor` bulk action with inline `request()->user()->hasRole(...)`/
 * `hasAnyRole(...)` checks (Bucket A per D6's triage). Unlike PrestamoResource/
 * RetanqueoResource, `GrupoResource` has NO static `canViewAny`/`canCreate`/
 * `canEdit`/`canDelete` overrides — so there is no Resource-vs-Policy drift to
 * resolve here; `GrupoPolicy`'s existing CRUD methods are untouched.
 *
 * This migration adds three new Policy methods (`verCampoAsesor`,
 * `verFiltroAsesor`, `cambiarAsesorMasivo`) for the three Bucket-A call sites,
 * each a verbatim copy of the closure it replaces.
 *
 * Deliberately DB-free (see PR2-PR10 environment constraint: the remote test
 * DB is unreachable in this sandbox): `hasRole`/`hasAnyRole` are stubbed via a
 * Mockery partial mock of `User`, matching the convention established in
 * `PagoPolicyMigrationParityTest`/`PrestamoPolicyMigrationParityTest`/
 * `RetanqueoPolicyMigrationParityTest`. `Grupo` uses `$fillable` (not
 * `$guarded`), so plain `new Grupo([...])` construction is safe here (no
 * mass-assignment DB-schema-check hang — see PR10's root-cause finding for
 * `$guarded` models).
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

function sameRoleSetGrupo(array $expected)
{
    return Mockery::on(fn ($actual) => is_array($actual) && count(array_diff($expected, $actual)) === 0 && count(array_diff($actual, $expected)) === 0);
}

// ─── verCampoAsesor (GrupoResource.php:59, form's asesor_id Select ->visible()) ───

it('verCampoAsesor preserves the role-axis outcome of the original hasAnyRole closure', function (string $role, bool $expected) {
    $policy = new GrupoPolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetGrupo(['super_admin', 'Jefe de operaciones']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones'], true));

    expect($policy->verCampoAsesor($user))->toBe($expected);
})->with([
    'super_admin -> visible' => ['super_admin', true],
    'Jefe de operaciones -> visible' => ['Jefe de operaciones', true],
    'Jefe de creditos -> hidden' => ['Jefe de creditos', false],
    'Asesor -> hidden' => ['Asesor', false],
]);

// ─── verFiltroAsesor (GrupoResource.php:363, table's asesor SelectFilter ->visible()) ───

it('verFiltroAsesor preserves the role-axis outcome of the original hasRole closure', function (string $role, bool $expected) {
    $policy = new GrupoPolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasRole')->with('Asesor')->andReturn($role === 'Asesor');

    expect($policy->verFiltroAsesor($user))->toBe($expected);
})->with([
    'Asesor -> hidden' => ['Asesor', false],
    'Jefe de creditos -> visible' => ['Jefe de creditos', true],
    'Jefe de operaciones -> visible' => ['Jefe de operaciones', true],
    'super_admin -> visible' => ['super_admin', true],
]);

// ─── cambiarAsesorMasivo (GrupoResource.php:412, bulk 'cambiar_asesor' ->visible()) ───

it('cambiarAsesorMasivo preserves the role-axis outcome of the original hasAnyRole closure', function (string $role, bool $expected) {
    $policy = new GrupoPolicy();

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('hasAnyRole')
        ->with(sameRoleSetGrupo(['super_admin', 'Jefe de operaciones']))
        ->andReturn(in_array($role, ['super_admin', 'Jefe de operaciones'], true));

    expect($policy->cambiarAsesorMasivo($user))->toBe($expected);
})->with([
    'super_admin -> visible' => ['super_admin', true],
    'Jefe de operaciones -> visible' => ['Jefe de operaciones', true],
    'Jefe de creditos -> hidden' => ['Jefe de creditos', false],
    'Asesor -> hidden' => ['Asesor', false],
]);
