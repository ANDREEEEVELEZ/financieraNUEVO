<?php

namespace App\Providers;

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
 * This unit (Slice 5a) registers ONLY the 11 EXISTING Policy classes.
 * Zero behavior change: it does not add, remove, or alter any authorization
 * rule, and creates no new Policy classes. The ~20 missing Policies (design
 * D7's remaining models) are a separate, later slice (5b).
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model => policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
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

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
