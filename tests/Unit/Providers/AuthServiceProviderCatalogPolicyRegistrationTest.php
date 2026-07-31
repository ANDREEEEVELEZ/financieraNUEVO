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
use Illuminate\Support\Facades\Gate;

/**
 * Slice 5b-catalog (design D7, spec Requirement 4.2/4.3): the 10 newly
 * created catalog-group Policies must be explicitly registered alongside
 * the 11 existing + 5 financial-group + 4 lifecycle-group ones (20 total so
 * far) in App\Providers\AuthServiceProvider — not solely relying on
 * Laravel's implicit naming-convention auto-discovery. This completes all
 * of Slice 5b (11 original + 20 newly-created = 31 total models minus the
 * 1 justified exclusion, GrupoCliente, per Scenario 4.3.b).
 *
 * Mirrors the assertion style of the Slice 5a/5b-financial/5b-lifecycle
 * tests: asserts against Gate::policies() (the explicitly-registered map)
 * to prove AuthServiceProvider itself does the registering — this fails
 * (RED) before the 10 new map entries exist, unlike a resolution-only
 * assertion which could pass via naming-convention auto-discovery alone.
 *
 * Deliberately DB-free — see AuthServiceProviderPolicyRegistrationTest.php's
 * docblock for the full rationale (Gate policy registration never touches
 * the database).
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

it('explicitly registers the 10 catalog-group models to their Policy via AuthServiceProvider (Gate::policies())', function () {
    $expected = [
        AuditLog::class => AuditLogPolicy::class,
        BusinessRuleConfig::class => BusinessRuleConfigPolicy::class,
        Categoria::class => CategoriaPolicy::class,
        ClienteScoring::class => ClienteScoringPolicy::class,
        ConsultaAsistente::class => ConsultaAsistentePolicy::class,
        MetaMensual::class => MetaMensualPolicy::class,
        MetricaDiariaAsesor::class => MetricaDiariaAsesorPolicy::class,
        MovimientoFinanciero::class => MovimientoFinancieroPolicy::class,
        Persona::class => PersonaPolicy::class,
        Subcategoria::class => SubcategoriaPolicy::class,
    ];

    $registered = Gate::policies();

    foreach ($expected as $model => $policy) {
        expect($registered)->toHaveKey($model);
        expect($registered[$model])->toBe($policy);
    }
});

it('resolves each of the 10 catalog-group models to its Policy via Gate::getPolicyFor()', function (string $model, string $policy) {
    expect(Gate::getPolicyFor($model))->toBeInstanceOf($policy);
})->with([
    'AuditLog' => [AuditLog::class, AuditLogPolicy::class],
    'BusinessRuleConfig' => [BusinessRuleConfig::class, BusinessRuleConfigPolicy::class],
    'Categoria' => [Categoria::class, CategoriaPolicy::class],
    'ClienteScoring' => [ClienteScoring::class, ClienteScoringPolicy::class],
    'ConsultaAsistente' => [ConsultaAsistente::class, ConsultaAsistentePolicy::class],
    'MetaMensual' => [MetaMensual::class, MetaMensualPolicy::class],
    'MetricaDiariaAsesor' => [MetricaDiariaAsesor::class, MetricaDiariaAsesorPolicy::class],
    'MovimientoFinanciero' => [MovimientoFinanciero::class, MovimientoFinancieroPolicy::class],
    'Persona' => [Persona::class, PersonaPolicy::class],
    'Subcategoria' => [Subcategoria::class, SubcategoriaPolicy::class],
]);
