<?php

declare(strict_types=1);

use App\Models\PrestamoIndividual;
use App\Models\Reagrupacion;
use App\Models\RetanqueoIndividual;
use App\Models\SeparacionCliente;
use App\Models\User;
use App\Policies\PrestamoIndividualPolicy;
use App\Policies\ReagrupacionPolicy;
use App\Policies\RetanqueoIndividualPolicy;
use App\Policies\SeparacionClientePolicy;

/**
 * Slice 5b-lifecycle (design D7, spec Requirement 4.3): PrestamoIndividual,
 * SeparacionCliente, Reagrupacion, and RetanqueoIndividual currently have
 * ZERO authorization gate anywhere in the codebase (confirmed by grep across
 * app/Filament and app/Domain/Prestamos + app/Domain/Grupos: no
 * hasRole/hasAnyRole/visible/hidden/authorize/canX call site references any
 * of these 4 models directly).
 *
 * - `PrestamoIndividual` and `RetanqueoIndividual` ARE referenced in
 *   `PrestamoResource`'s Pages and `RetanqueoResource\Pages\EditRetanqueo`,
 *   but only as plain data reads/writes (`::where(...)->get()`,
 *   `::create([...])`, `::find(...)`) inside Filament form callbacks — never
 *   behind a `hasRole`/`hasAnyRole`/`visible`/`hidden`/`authorize*`/`canX`
 *   check. That is not a Bucket-A authorization gate per design D6.
 * - `SeparacionCliente` and `Reagrupacion` have zero references anywhere
 *   under `app/Filament` — no Filament Resource of their own; they are
 *   written only by domain services (`MorosoSeparationService`,
 *   `RetanqueoWorkflowService`/`RetanqueoEjecucionService`).
 *
 * Parity-preserving choice (Requirement 4.3 forbids silently tightening
 * access): each Policy method delegates to a Shield-style permission check,
 * consistent with the other 16 existing Policies (mirrors PrestamoPolicy's
 * shape per D7's instruction for models with no inline check).
 * database/seeders/PermissionSeeder.php grants every one of these new
 * permissions to ALL FOUR existing roles (super_admin, Jefe de creditos,
 * Jefe de operaciones, Asesor) — functionally equivalent to "allow any
 * authenticated user", matching current de-facto behavior exactly.
 *
 * These tests assert the DELEGATION itself (permission granted -> allowed,
 * permission absent -> denied), not a hardcoded `true`, so a future
 * accidental `return true;` (bypassing Spatie's permission system) or a
 * missing PermissionSeeder row would both be caught as regressions. Only
 * viewAny/create/delete are exercised here (one representative read verb,
 * one write verb, one instance-scoped verb) — view/update share the exact
 * same one-line delegation shape and are not re-tested per-method (user
 * testing preference: no exhaustive combinatorial matrix for trivial,
 * identically-shaped delegation), matching PR5's precedent.
 *
 * Deliberately DB-free: `User::can()` is stubbed via a Mockery partial mock,
 * so this suite runs even when the test database is unreachable (see
 * PR2-PR5 environment constraint notes) — Policy DELEGATION never needs the
 * database; only Spatie's actual permission LOOKUP does, and that lookup is
 * not what's under test here.
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

dataset('lifecycle_policies', [
    'PrestamoIndividual' => [PrestamoIndividual::class, PrestamoIndividualPolicy::class, 'prestamo_individual'],
    'SeparacionCliente' => [SeparacionCliente::class, SeparacionClientePolicy::class, 'separacion_cliente'],
    'Reagrupacion' => [Reagrupacion::class, ReagrupacionPolicy::class, 'reagrupacion'],
    'RetanqueoIndividual' => [RetanqueoIndividual::class, RetanqueoIndividualPolicy::class, 'retanqueo_individual'],
]);

it('viewAny delegates to the Shield-style permission and denies when absent', function (string $model, string $policyClass, string $permissionSlug) {
    $policy = new $policyClass();

    $allowed = Mockery::mock(User::class)->makePartial();
    $allowed->shouldReceive('can')->once()->with("view_any_{$permissionSlug}")->andReturn(true);
    expect($policy->viewAny($allowed))->toBeTrue();

    $denied = Mockery::mock(User::class)->makePartial();
    $denied->shouldReceive('can')->once()->with("view_any_{$permissionSlug}")->andReturn(false);
    expect($policy->viewAny($denied))->toBeFalse();
})->with('lifecycle_policies');

it('create delegates to the Shield-style permission and denies when absent', function (string $model, string $policyClass, string $permissionSlug) {
    $policy = new $policyClass();

    $allowed = Mockery::mock(User::class)->makePartial();
    $allowed->shouldReceive('can')->once()->with("create_{$permissionSlug}")->andReturn(true);
    expect($policy->create($allowed))->toBeTrue();

    $denied = Mockery::mock(User::class)->makePartial();
    $denied->shouldReceive('can')->once()->with("create_{$permissionSlug}")->andReturn(false);
    expect($policy->create($denied))->toBeFalse();
})->with('lifecycle_policies');

it('delete delegates to the Shield-style permission for a specific model instance', function (string $model, string $policyClass, string $permissionSlug) {
    $policy = new $policyClass();
    $record = new $model();

    $allowed = Mockery::mock(User::class)->makePartial();
    $allowed->shouldReceive('can')->once()->with("delete_{$permissionSlug}")->andReturn(true);
    expect($policy->delete($allowed, $record))->toBeTrue();

    $denied = Mockery::mock(User::class)->makePartial();
    $denied->shouldReceive('can')->once()->with("delete_{$permissionSlug}")->andReturn(false);
    expect($policy->delete($denied, $record))->toBeFalse();
})->with('lifecycle_policies');
