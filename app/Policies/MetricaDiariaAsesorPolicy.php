<?php

namespace App\Policies;

use App\Models\MetricaDiariaAsesor;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * MetricaDiariaAsesor has no Filament Resource of its own — it is read
 * inline by `AsesorPage`/`AsesorDashboard` (`::where('asesor_id', ...)`,
 * `::whereDate(...)`), with ZERO authorization check anywhere (design D7 /
 * spec Requirement 4.3; confirmed by grep: every reference is a plain read,
 * never a hasRole/hasAnyRole/visible/hidden/authorize/canX gate).
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 26 existing Policies (mirrors PrestamoPolicy's shape per
 * design D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any call site — this Policy is additive-only in
 * this change (Slice 5b-catalog).
 */
class MetricaDiariaAsesorPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_metrica_diaria_asesor');
    }

    public function view(User $user, MetricaDiariaAsesor $metricaDiariaAsesor): bool
    {
        return $user->can('view_metrica_diaria_asesor');
    }

    public function create(User $user): bool
    {
        return $user->can('create_metrica_diaria_asesor');
    }

    public function update(User $user, MetricaDiariaAsesor $metricaDiariaAsesor): bool
    {
        return $user->can('update_metrica_diaria_asesor');
    }

    public function delete(User $user, MetricaDiariaAsesor $metricaDiariaAsesor): bool
    {
        return $user->can('delete_metrica_diaria_asesor');
    }
}
