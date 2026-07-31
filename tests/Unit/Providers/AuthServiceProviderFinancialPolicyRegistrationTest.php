<?php

declare(strict_types=1);

use App\Models\AjusteDeuda;
use App\Models\AplicacionPago;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Mora;
use App\Policies\AjusteDeudaPolicy;
use App\Policies\AplicacionPagoPolicy;
use App\Policies\CuotaIndividualPolicy;
use App\Policies\CuotasGrupalesPolicy;
use App\Policies\MoraPolicy;
use Illuminate\Support\Facades\Gate;

/**
 * Slice 5b-financial (design D7, spec Requirement 4.2/4.3): the 5 newly
 * created financial-group Policies (Mora, CuotaIndividual, CuotasGrupales,
 * AplicacionPago, AjusteDeuda) must be explicitly registered alongside the
 * 11 existing ones in App\Providers\AuthServiceProvider — not solely
 * relying on Laravel's implicit naming-convention auto-discovery.
 *
 * Mirrors the assertion style of the Slice 5a test
 * (AuthServiceProviderPolicyRegistrationTest.php): asserts against
 * Gate::policies() (the explicitly-registered map) to prove
 * AuthServiceProvider itself does the registering — this fails (RED) before
 * the 5 new map entries exist, unlike a resolution-only assertion which
 * could pass via naming-convention auto-discovery alone.
 *
 * Deliberately DB-free — see AuthServiceProviderPolicyRegistrationTest.php's
 * docblock for the full rationale (Gate policy registration never touches
 * the database).
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

it('explicitly registers the 5 financial-group models to their Policy via AuthServiceProvider (Gate::policies())', function () {
    $expected = [
        Mora::class => MoraPolicy::class,
        CuotaIndividual::class => CuotaIndividualPolicy::class,
        CuotasGrupales::class => CuotasGrupalesPolicy::class,
        AplicacionPago::class => AplicacionPagoPolicy::class,
        AjusteDeuda::class => AjusteDeudaPolicy::class,
    ];

    $registered = Gate::policies();

    foreach ($expected as $model => $policy) {
        expect($registered)->toHaveKey($model);
        expect($registered[$model])->toBe($policy);
    }
});

it('resolves each of the 5 financial-group models to its Policy via Gate::getPolicyFor()', function (string $model, string $policy) {
    expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
})->with([
    'Mora' => [Mora::class, MoraPolicy::class],
    'CuotaIndividual' => [CuotaIndividual::class, CuotaIndividualPolicy::class],
    'CuotasGrupales' => [CuotasGrupales::class, CuotasGrupalesPolicy::class],
    'AplicacionPago' => [AplicacionPago::class, AplicacionPagoPolicy::class],
    'AjusteDeuda' => [AjusteDeuda::class, AjusteDeudaPolicy::class],
]);
