<?php

namespace App\Providers;

use App\Models\AjusteDeuda;
use App\Models\AplicacionPago;
use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Egreso;
use App\Models\Grupo;
use App\Models\Ingreso;
use App\Models\Mora;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Models\ProductoFinanciero;
use App\Models\Reagrupacion;
use App\Models\Retanqueo;
use App\Models\RetanqueoIndividual;
use App\Models\SeparacionCliente;
use App\Models\User;
use App\Policies\AjusteDeudaPolicy;
use App\Policies\AplicacionPagoPolicy;
use App\Policies\AsesorPolicy;
use App\Policies\ClientePolicy;
use App\Policies\CuotaIndividualPolicy;
use App\Policies\CuotasGrupalesPolicy;
use App\Policies\EgresoPolicy;
use App\Policies\GrupoPolicy;
use App\Policies\IngresoPolicy;
use App\Policies\MoraPolicy;
use App\Policies\PagoPolicy;
use App\Policies\PrestamoIndividualPolicy;
use App\Policies\PrestamoPolicy;
use App\Policies\ProductoFinancieroPolicy;
use App\Policies\ReagrupacionPolicy;
use App\Policies\RetanqueoIndividualPolicy;
use App\Policies\RetanqueoPolicy;
use App\Policies\RolePolicy;
use App\Policies\SeparacionClientePolicy;
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
 * 5b-lifecycle adds the second group: PrestamoIndividual, SeparacionCliente,
 * Reagrupacion, RetanqueoIndividual. All 9 of these are newly-created and
 * additive-only in this change — they are not yet referenced by any
 * Filament call site (that migration is Slice 5c-5g, later PRs). See each
 * Policy class's docblock for its rule-parity derivation. The remaining
 * ~11 models (catalog group) land in a subsequent PR and will extend this
 * same map.
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
        Cliente::class => ClientePolicy::class,
        CuotaIndividual::class => CuotaIndividualPolicy::class,
        CuotasGrupales::class => CuotasGrupalesPolicy::class,
        Egreso::class => EgresoPolicy::class,
        Grupo::class => GrupoPolicy::class,
        Ingreso::class => IngresoPolicy::class,
        Mora::class => MoraPolicy::class,
        Pago::class => PagoPolicy::class,
        Prestamo::class => PrestamoPolicy::class,
        PrestamoIndividual::class => PrestamoIndividualPolicy::class,
        ProductoFinanciero::class => ProductoFinancieroPolicy::class,
        Reagrupacion::class => ReagrupacionPolicy::class,
        Retanqueo::class => RetanqueoPolicy::class,
        RetanqueoIndividual::class => RetanqueoIndividualPolicy::class,
        Role::class => RolePolicy::class,
        SeparacionCliente::class => SeparacionClientePolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
