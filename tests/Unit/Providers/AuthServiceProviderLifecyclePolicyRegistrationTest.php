<?php

declare(strict_types=1);

use App\Models\PrestamoIndividual;
use App\Models\Reagrupacion;
use App\Models\RetanqueoIndividual;
use App\Models\SeparacionCliente;
use App\Policies\PrestamoIndividualPolicy;
use App\Policies\ReagrupacionPolicy;
use App\Policies\RetanqueoIndividualPolicy;
use App\Policies\SeparacionClientePolicy;
use Illuminate\Support\Facades\Gate;

/**
 * Slice 5b-lifecycle (design D7, spec Requirement 4.2/4.3): the 4 newly
 * created lifecycle-group Policies (PrestamoIndividual, SeparacionCliente,
 * Reagrupacion, RetanqueoIndividual) must be explicitly registered alongside
 * the 11 existing + 5 financial-group ones in App\Providers\AuthServiceProvider
 * — not solely relying on Laravel's implicit naming-convention auto-discovery.
 *
 * Mirrors the assertion style of the Slice 5a/5b-financial tests: asserts
 * against Gate::policies() (the explicitly-registered map) to prove
 * AuthServiceProvider itself does the registering — this fails (RED) before
 * the 4 new map entries exist, unlike a resolution-only assertion which
 * could pass via naming-convention auto-discovery alone.
 *
 * Deliberately DB-free — see AuthServiceProviderPolicyRegistrationTest.php's
 * docblock for the full rationale (Gate policy registration never touches
 * the database).
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

it('explicitly registers the 4 lifecycle-group models to their Policy via AuthServiceProvider (Gate::policies())', function () {
    $expected = [
        PrestamoIndividual::class => PrestamoIndividualPolicy::class,
        SeparacionCliente::class => SeparacionClientePolicy::class,
        Reagrupacion::class => ReagrupacionPolicy::class,
        RetanqueoIndividual::class => RetanqueoIndividualPolicy::class,
    ];

    $registered = Gate::policies();

    foreach ($expected as $model => $policy) {
        expect($registered)->toHaveKey($model);
        expect($registered[$model])->toBe($policy);
    }
});

it('resolves each of the 4 lifecycle-group models to its Policy via Gate::getPolicyFor()', function (string $model, string $policy) {
    expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
})->with([
    'PrestamoIndividual' => [PrestamoIndividual::class, PrestamoIndividualPolicy::class],
    'SeparacionCliente' => [SeparacionCliente::class, SeparacionClientePolicy::class],
    'Reagrupacion' => [Reagrupacion::class, ReagrupacionPolicy::class],
    'RetanqueoIndividual' => [RetanqueoIndividual::class, RetanqueoIndividualPolicy::class],
]);
