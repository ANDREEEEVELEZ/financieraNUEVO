<?php

declare(strict_types=1);

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\Egreso;
use App\Models\Grupo;
use App\Models\Ingreso;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\ProductoFinanciero;
use App\Models\Retanqueo;
use App\Models\User;
use App\Policies\AsesorPolicy;
use App\Policies\ClientePolicy;
use App\Policies\EgresoPolicy;
use App\Policies\GrupoPolicy;
use App\Policies\IngresoPolicy;
use App\Policies\PagoPolicy;
use App\Policies\PrestamoPolicy;
use App\Policies\ProductoFinancieroPolicy;
use App\Policies\RetanqueoPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * Slice 5a (design D7, spec Requirement 4.2 / Scenario 4.2.a): every one of
 * the 11 EXISTING Policies must resolve via an EXPLICIT Gate::policy()
 * registration (App\Providers\AuthServiceProvider), not solely Laravel's
 * implicit naming-convention auto-discovery.
 *
 * This asserts against Gate::policies() (the explicitly-registered map),
 * not just Gate::getPolicyFor() — resolution alone is not a strong enough
 * assertion here, because auto-discovery already resolves the 10 App\Models\*
 * cases today, and `bezhansalleh/filament-shield` independently registers
 * Role => RolePolicy via its own service provider when
 * `filament-shield.register_role_policy.enabled` is true. Neither of those
 * is *this* project's explicit, reviewable registration point (design D7);
 * asserting on Gate::policies() proves AuthServiceProvider itself is the one
 * doing the registering, and fails (RED) before it exists.
 *
 * Zero behavior change: this only makes the existing (mostly auto-discovered)
 * bindings explicit and reviewable. It does not add, remove, or alter any
 * authorization rule, and creates no new Policy classes.
 *
 * Deliberately DB-free: uses the base Illuminate\Foundation\Testing\TestCase
 * (boots the real app via bootstrap/app.php, no RefreshDatabase / role
 * seeding), so this guard runs even when the test database is unreachable —
 * Gate policy registration never touches the database.
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

it('explicitly registers all 11 existing models to their Policy via AuthServiceProvider (Gate::policies())', function () {
    $expected = [
        Asesor::class => AsesorPolicy::class,
        Cliente::class => ClientePolicy::class,
        Egreso::class => EgresoPolicy::class,
        Grupo::class => GrupoPolicy::class,
        Ingreso::class => IngresoPolicy::class,
        Pago::class => PagoPolicy::class,
        Prestamo::class => PrestamoPolicy::class,
        ProductoFinanciero::class => ProductoFinancieroPolicy::class,
        Retanqueo::class => RetanqueoPolicy::class,
        Role::class => RolePolicy::class,
        User::class => UserPolicy::class,
    ];

    $registered = Gate::policies();

    foreach ($expected as $model => $policy) {
        expect($registered)->toHaveKey($model);
        expect($registered[$model])->toBe($policy);
    }
});

it('resolves each of the 11 existing models to its Policy via Gate::getPolicyFor()', function (string $model, string $policy) {
    expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
})->with([
    'Asesor' => [Asesor::class, AsesorPolicy::class],
    'Cliente' => [Cliente::class, ClientePolicy::class],
    'Egreso' => [Egreso::class, EgresoPolicy::class],
    'Grupo' => [Grupo::class, GrupoPolicy::class],
    'Ingreso' => [Ingreso::class, IngresoPolicy::class],
    'Pago' => [Pago::class, PagoPolicy::class],
    'Prestamo' => [Prestamo::class, PrestamoPolicy::class],
    'ProductoFinanciero' => [ProductoFinanciero::class, ProductoFinancieroPolicy::class],
    'Retanqueo' => [Retanqueo::class, RetanqueoPolicy::class],
    'Role (Spatie)' => [Role::class, RolePolicy::class],
    'User' => [User::class, UserPolicy::class],
]);
