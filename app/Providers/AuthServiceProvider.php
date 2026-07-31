<?php

namespace App\Providers;

use App\Models\AjusteDeuda;
use App\Models\AplicacionPago;
use App\Models\Asesor;
use App\Models\AuditLog;
use App\Models\BusinessRuleConfig;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\ClienteScoring;
use App\Models\ConsultaAsistente;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Egreso;
use App\Models\Grupo;
use App\Models\Ingreso;
use App\Models\MetaMensual;
use App\Models\MetricaDiariaAsesor;
use App\Models\Mora;
use App\Models\MovimientoFinanciero;
use App\Models\Pago;
use App\Models\Persona;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Models\ProductoFinanciero;
use App\Models\Reagrupacion;
use App\Models\Retanqueo;
use App\Models\RetanqueoIndividual;
use App\Models\SeparacionCliente;
use App\Models\Subcategoria;
use App\Models\User;
use App\Policies\AjusteDeudaPolicy;
use App\Policies\AplicacionPagoPolicy;
use App\Policies\AsesorPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BusinessRuleConfigPolicy;
use App\Policies\CategoriaPolicy;
use App\Policies\ClientePolicy;
use App\Policies\ClienteScoringPolicy;
use App\Policies\ConsultaAsistentePolicy;
use App\Policies\CuotaIndividualPolicy;
use App\Policies\CuotasGrupalesPolicy;
use App\Policies\EgresoPolicy;
use App\Policies\GrupoPolicy;
use App\Policies\IngresoPolicy;
use App\Policies\MetaMensualPolicy;
use App\Policies\MetricaDiariaAsesorPolicy;
use App\Policies\MoraPolicy;
use App\Policies\MovimientoFinancieroPolicy;
use App\Policies\PagoPolicy;
use App\Policies\PersonaPolicy;
use App\Policies\PrestamoIndividualPolicy;
use App\Policies\PrestamoPolicy;
use App\Policies\ProductoFinancieroPolicy;
use App\Policies\ReagrupacionPolicy;
use App\Policies\RetanqueoIndividualPolicy;
use App\Policies\RetanqueoPolicy;
use App\Policies\RolePolicy;
use App\Policies\SeparacionClientePolicy;
use App\Policies\SubcategoriaPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

/**
 * Explicit Gate::policy() registration for every model with a Policy class.
 *
 * Design D7 / spec Requirement 4.2: Laravel's implicit naming-convention
 * auto-discovery already resolves most of these (App\Models\* -> matching
 * App\Policies\*Policy), but relying on it is unverified/fragile for
 * non-App\Models\* targets and silently no-ops on a typo'd class name. This
 * provider makes the mapping explicit and reviewable in one canonical place,
 * instead of it living implicitly in a naming convention (or, for `Role`,
 * inside `bezhansalleh/filament-shield`'s own opt-in registration).
 *
 * Slice 5a registered the 11 EXISTING Policy classes (zero behavior change:
 * no new Policies, no call-site changes). Slice 5b-financial added the first
 * group of the ~20 missing Policies (design D7's remaining models): Mora,
 * CuotaIndividual, CuotasGrupales, AplicacionPago, AjusteDeuda. Slice
 * 5b-lifecycle added the second group: PrestamoIndividual, SeparacionCliente,
 * Reagrupacion, RetanqueoIndividual. Slice 5b-catalog (this PR) adds the
 * third and FINAL group — the remaining 10 models: AuditLog,
 * BusinessRuleConfig, Categoria, ClienteScoring, ConsultaAsistente,
 * MetaMensual, MetricaDiariaAsesor, MovimientoFinanciero, Persona,
 * Subcategoria. This completes Slice 5b: 11 original + 5 financial +
 * 4 lifecycle + 10 catalog = 30 registered Policies.
 *
 * All 19 newly-created Policies (5 financial + 4 lifecycle + 10 catalog) are
 * additive-only in this change — none are yet referenced by any Filament
 * call site (that migration is Slice 5c-5g, later PRs). See each Policy
 * class's docblock for its rule-parity derivation.
 *
 * Design D7 counts 31 total models. The 31st, `GrupoCliente`, is a
 * DELIBERATE EXCLUSION (spec Scenario 4.3.b) rather than an oversight: it is
 * a pure join-table model for the `grupo_cliente` pivot between `Grupo` and
 * `Cliente`, accessed exclusively via their `belongsToMany()` relationships
 * — confirmed by grep, `GrupoCliente::` has zero direct call sites anywhere
 * in `app/`, it is never wired as an explicit pivot model via `->using()`,
 * and it has no Filament Resource of its own. It has no direct
 * authorization surface for a Policy to protect.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model => policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        AjusteDeuda::class => AjusteDeudaPolicy::class,
        AplicacionPago::class => AplicacionPagoPolicy::class,
        Asesor::class => AsesorPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        BusinessRuleConfig::class => BusinessRuleConfigPolicy::class,
        Categoria::class => CategoriaPolicy::class,
        Cliente::class => ClientePolicy::class,
        ClienteScoring::class => ClienteScoringPolicy::class,
        ConsultaAsistente::class => ConsultaAsistentePolicy::class,
        CuotaIndividual::class => CuotaIndividualPolicy::class,
        CuotasGrupales::class => CuotasGrupalesPolicy::class,
        Egreso::class => EgresoPolicy::class,
        Grupo::class => GrupoPolicy::class,
        Ingreso::class => IngresoPolicy::class,
        MetaMensual::class => MetaMensualPolicy::class,
        MetricaDiariaAsesor::class => MetricaDiariaAsesorPolicy::class,
        Mora::class => MoraPolicy::class,
        MovimientoFinanciero::class => MovimientoFinancieroPolicy::class,
        Pago::class => PagoPolicy::class,
        Persona::class => PersonaPolicy::class,
        Prestamo::class => PrestamoPolicy::class,
        PrestamoIndividual::class => PrestamoIndividualPolicy::class,
        ProductoFinanciero::class => ProductoFinancieroPolicy::class,
        Reagrupacion::class => ReagrupacionPolicy::class,
        Retanqueo::class => RetanqueoPolicy::class,
        RetanqueoIndividual::class => RetanqueoIndividualPolicy::class,
        Role::class => RolePolicy::class,
        SeparacionCliente::class => SeparacionClientePolicy::class,
        Subcategoria::class => SubcategoriaPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
