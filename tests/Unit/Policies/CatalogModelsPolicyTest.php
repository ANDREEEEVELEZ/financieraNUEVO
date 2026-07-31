<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\BusinessRuleConfig;
use App\Models\Categoria;
use App\Models\ClienteScoring;
use App\Models\ConsultaAsistente;
use App\Models\MetaMensual;
use App\Models\MetricaDiariaAsesor;
use App\Models\MovimientoFinanciero;
use App\Models\Persona;
use App\Models\Subcategoria;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\BusinessRuleConfigPolicy;
use App\Policies\CategoriaPolicy;
use App\Policies\ClienteScoringPolicy;
use App\Policies\ConsultaAsistentePolicy;
use App\Policies\MetaMensualPolicy;
use App\Policies\MetricaDiariaAsesorPolicy;
use App\Policies\MovimientoFinancieroPolicy;
use App\Policies\PersonaPolicy;
use App\Policies\SubcategoriaPolicy;

/**
 * Slice 5b-catalog (design D7, spec Requirement 4.3): the last group of the
 * ~20 missing Policies. Covers the remaining 10 catalog models — the 11th
 * catalog model, `GrupoCliente`, is EXCLUDED with justification (Scenario
 * 4.3.b): it is a pure join-table model for the `grupo_cliente` pivot
 * between `Grupo` and `Cliente`, accessed EXCLUSIVELY via their
 * `belongsToMany()` relationships (`Grupo::clientes()`, `Cliente::grupos()`)
 * — confirmed by grep: `GrupoCliente::` has ZERO direct call sites anywhere
 * in `app/` (no controller, service, or Filament Resource ever queries or
 * mutates it directly), it is never wired as a pivot model via `->using()`,
 * and it has no Filament Resource of its own. It has no direct authorization
 * surface to protect — a Policy for it would never be invoked by anything.
 *
 * All 10 models covered here currently have ZERO authorization gate anywhere
 * in the codebase (confirmed by grep across app/Filament, app/Domain,
 * app/Services): every reference is a plain data read/write
 * (`::where(...)->exists()`, `::create(...)`, `::find(...)`) — never behind
 * a hasRole/hasAnyRole/visible/hidden/authorize/canX gate. None have a
 * dedicated Filament Resource.
 *
 * Parity-preserving choice (Requirement 4.3 forbids silently tightening
 * access): each Policy method delegates to a Shield-style permission check,
 * consistent with the other 20 existing Policies (mirrors PrestamoPolicy's
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
 * identically-shaped delegation), matching PR5/PR6's precedent.
 *
 * Deliberately DB-free: `User::can()` is stubbed via a Mockery partial mock,
 * so this suite runs even when the test database is unreachable (see
 * PR2-PR6 environment constraint notes) — Policy DELEGATION never needs the
 * database; only Spatie's actual permission LOOKUP does, and that lookup is
 * not what's under test here.
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

dataset('catalog_policies', [
    'AuditLog' => [AuditLog::class, AuditLogPolicy::class, 'audit_log'],
    'BusinessRuleConfig' => [BusinessRuleConfig::class, BusinessRuleConfigPolicy::class, 'business_rule_config'],
    'Categoria' => [Categoria::class, CategoriaPolicy::class, 'categoria'],
    'ClienteScoring' => [ClienteScoring::class, ClienteScoringPolicy::class, 'cliente_scoring'],
    'ConsultaAsistente' => [ConsultaAsistente::class, ConsultaAsistentePolicy::class, 'consulta_asistente'],
    'MetaMensual' => [MetaMensual::class, MetaMensualPolicy::class, 'meta_mensual'],
    'MetricaDiariaAsesor' => [MetricaDiariaAsesor::class, MetricaDiariaAsesorPolicy::class, 'metrica_diaria_asesor'],
    'MovimientoFinanciero' => [MovimientoFinanciero::class, MovimientoFinancieroPolicy::class, 'movimiento_financiero'],
    'Persona' => [Persona::class, PersonaPolicy::class, 'persona'],
    'Subcategoria' => [Subcategoria::class, SubcategoriaPolicy::class, 'subcategoria'],
]);

it('viewAny delegates to the Shield-style permission and denies when absent', function (string $model, string $policyClass, string $permissionSlug) {
    $policy = new $policyClass();

    $allowed = Mockery::mock(User::class)->makePartial();
    $allowed->shouldReceive('can')->once()->with("view_any_{$permissionSlug}")->andReturn(true);
    expect($policy->viewAny($allowed))->toBeTrue();

    $denied = Mockery::mock(User::class)->makePartial();
    $denied->shouldReceive('can')->once()->with("view_any_{$permissionSlug}")->andReturn(false);
    expect($policy->viewAny($denied))->toBeFalse();
})->with('catalog_policies');

it('create delegates to the Shield-style permission and denies when absent', function (string $model, string $policyClass, string $permissionSlug) {
    $policy = new $policyClass();

    $allowed = Mockery::mock(User::class)->makePartial();
    $allowed->shouldReceive('can')->once()->with("create_{$permissionSlug}")->andReturn(true);
    expect($policy->create($allowed))->toBeTrue();

    $denied = Mockery::mock(User::class)->makePartial();
    $denied->shouldReceive('can')->once()->with("create_{$permissionSlug}")->andReturn(false);
    expect($policy->create($denied))->toBeFalse();
})->with('catalog_policies');

it('delete delegates to the Shield-style permission for a specific model instance', function (string $model, string $policyClass, string $permissionSlug) {
    $policy = new $policyClass();
    $record = new $model();

    $allowed = Mockery::mock(User::class)->makePartial();
    $allowed->shouldReceive('can')->once()->with("delete_{$permissionSlug}")->andReturn(true);
    expect($policy->delete($allowed, $record))->toBeTrue();

    $denied = Mockery::mock(User::class)->makePartial();
    $denied->shouldReceive('can')->once()->with("delete_{$permissionSlug}")->andReturn(false);
    expect($policy->delete($denied, $record))->toBeFalse();
})->with('catalog_policies');
