<?php

namespace App\Policies;

use App\Models\CuotasGrupales;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * CuotasGrupales has a Filament Resource (CuotasResource). At the time this
 * Policy was created (Slice 5b-financial, PR5), CuotasResource::getTableQuery()
 * data-scoped by asesor (Bucket B) and CuotasResource::canCreate() was
 * hardcoded false for everyone (a product decision, not a role gate); the
 * CRUD methods below mirror PrestamoPolicy's Shield shape per design D7's
 * instruction for models with no inline check at the time. PermissionSeeder
 * grants every CRUD permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * CORRECTION (Slice 5g, PR12): PR5's docblock originally classified
 * `CuotasResource`'s asesor-column `->visible()` call as Bucket C (UI
 * cosmetics). New evidence — the repeated, 3x-confirmed precedent from
 * `PrestamoPolicy::verFiltroAsesor` (PR9), `RetanqueoPolicy::verColumnaAsesor`
 * (PR10), and `GrupoPolicy::verFiltroAsesor` (PR11) — shows this exact shape
 * (`->visible(fn() => !hasRole('Asesor'))`, hiding the asesor identity column
 * from Asesor-role users) is Bucket A per D6's literal signature
 * (`->visible()`), not Bucket C: it is an information-disclosure
 * authorization decision, not merely cosmetic. `verColumnaAsesor()` below
 * migrates that call site (and its sibling in `CuotasVigentesWidget`, same
 * model, same check, reused as one Policy method) accordingly.
 */
class CuotasGrupalesPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_cuotas_grupales');
    }

    public function view(User $user, CuotasGrupales $cuotasGrupales): bool
    {
        return $user->can('view_cuotas_grupales');
    }

    public function create(User $user): bool
    {
        return $user->can('create_cuotas_grupales');
    }

    public function update(User $user, CuotasGrupales $cuotasGrupales): bool
    {
        return $user->can('update_cuotas_grupales');
    }

    public function delete(User $user, CuotasGrupales $cuotasGrupales): bool
    {
        return $user->can('delete_cuotas_grupales');
    }

    /**
     * Slice 5g: gates the asesor identity column's visibility in
     * `CuotasResource::table()` and `CuotasVigentesWidget::table()` — both
     * call sites share the exact same closure (`!hasRole('Asesor')`), so this
     * single Policy method covers both.
     */
    public function verColumnaAsesor(User $user): bool
    {
        return ! $user->hasRole('Asesor');
    }
}
