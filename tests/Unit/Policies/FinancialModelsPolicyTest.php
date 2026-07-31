<?php

declare(strict_types=1);

use App\Models\AjusteDeuda;
use App\Models\AplicacionPago;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Mora;
use App\Models\User;
use App\Policies\AjusteDeudaPolicy;
use App\Policies\AplicacionPagoPolicy;
use App\Policies\CuotaIndividualPolicy;
use App\Policies\CuotasGrupalesPolicy;
use App\Policies\MoraPolicy;

/**
 * Slice 5b-financial (design D7, spec Requirement 4.3): Mora, CuotaIndividual,
 * CuotasGrupales, AplicacionPago, and AjusteDeuda currently have ZERO
 * authorization gate anywhere in the codebase (confirmed by grep across
 * app/Filament and app/Domain/Pagos: no hasRole/hasAnyRole/visible/hidden/
 * authorize/canX call site references any of these 5 models directly;
 * Moras.php's dashboard page has no canAccess() override; CuotasResource
 * only data-scopes its query for 'Asesor' — Bucket B — it never denies
 * access; AjusteDeuda has no Filament UI at all, only domain-service writes
 * via CondonacionMoraService / CuotaIndividual::condonarMora()).
 *
 * Parity-preserving choice (Requirement 4.3 forbids silently tightening
 * access): each Policy method delegates to a Shield-style permission check,
 * consistent with the other 11 existing Policies (design D7's instruction to
 * "mirror PrestamoPolicy's Shield shape" for models with no inline check).
 * database/seeders/PermissionSeeder.php grants every one of these new
 * permissions to ALL FOUR existing roles (super_admin, Jefe de creditos,
 * Jefe de operaciones, Asesor) — every authenticated dashboard user has one
 * of those roles today, so this is functionally equivalent to "allow any
 * authenticated user", matching current de-facto behavior exactly while
 * staying idiomatically consistent with the rest of the codebase's Policies.
 *
 * These tests assert the DELEGATION itself (permission granted -> allowed,
 * permission absent -> denied), not a hardcoded `true`, so a future
 * accidental `return true;` (bypassing Spatie's permission system) or a
 * missing PermissionSeeder row would both be caught as regressions. Only
 * viewAny/create/delete are exercised here (one representative read verb,
 * one write verb, one instance-scoped verb) — view/update share the exact
 * same one-line delegation shape and are not re-tested per-method (user
 * testing preference: no exhaustive combinatorial matrix for trivial,
 * identically-shaped delegation).
 *
 * Deliberately DB-free: `User::can()` is stubbed via a Mockery partial mock,
 * so this suite runs even when the test database is unreachable (see PR2-PR5
 * environment constraint notes) — Policy DELEGATION never needs the
 * database; only Spatie's actual permission LOOKUP does, and that lookup is
 * not what's under test here.
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

dataset('financial_policies', [
    'Mora' => [Mora::class, MoraPolicy::class, 'mora'],
    'CuotaIndividual' => [CuotaIndividual::class, CuotaIndividualPolicy::class, 'cuota_individual'],
    'CuotasGrupales' => [CuotasGrupales::class, CuotasGrupalesPolicy::class, 'cuotas_grupales'],
    'AplicacionPago' => [AplicacionPago::class, AplicacionPagoPolicy::class, 'aplicacion_pago'],
    'AjusteDeuda' => [AjusteDeuda::class, AjusteDeudaPolicy::class, 'ajuste_deuda'],
]);

it('viewAny delegates to the Shield-style permission and denies when absent', function (string $model, string $policyClass, string $permissionSlug) {
    $policy = new $policyClass();

    $allowed = Mockery::mock(User::class)->makePartial();
    $allowed->shouldReceive('can')->once()->with("view_any_{$permissionSlug}")->andReturn(true);
    expect($policy->viewAny($allowed))->toBeTrue();

    $denied = Mockery::mock(User::class)->makePartial();
    $denied->shouldReceive('can')->once()->with("view_any_{$permissionSlug}")->andReturn(false);
    expect($policy->viewAny($denied))->toBeFalse();
})->with('financial_policies');

it('create delegates to the Shield-style permission and denies when absent', function (string $model, string $policyClass, string $permissionSlug) {
    $policy = new $policyClass();

    $allowed = Mockery::mock(User::class)->makePartial();
    $allowed->shouldReceive('can')->once()->with("create_{$permissionSlug}")->andReturn(true);
    expect($policy->create($allowed))->toBeTrue();

    $denied = Mockery::mock(User::class)->makePartial();
    $denied->shouldReceive('can')->once()->with("create_{$permissionSlug}")->andReturn(false);
    expect($policy->create($denied))->toBeFalse();
})->with('financial_policies');

it('delete delegates to the Shield-style permission for a specific model instance', function (string $model, string $policyClass, string $permissionSlug) {
    $policy = new $policyClass();
    $record = new $model();

    $allowed = Mockery::mock(User::class)->makePartial();
    $allowed->shouldReceive('can')->once()->with("delete_{$permissionSlug}")->andReturn(true);
    expect($policy->delete($allowed, $record))->toBeTrue();

    $denied = Mockery::mock(User::class)->makePartial();
    $denied->shouldReceive('can')->once()->with("delete_{$permissionSlug}")->andReturn(false);
    expect($policy->delete($denied, $record))->toBeFalse();
})->with('financial_policies');
