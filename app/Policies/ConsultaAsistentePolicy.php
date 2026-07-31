<?php

namespace App\Policies;

use App\Models\ConsultaAsistente;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ConsultaAsistente has no Filament UI and no controller/service wiring yet
 * — it is scaffolded (model + factory + a dedicated Unit test) but not yet
 * exposed anywhere in the application (design D7 / spec Requirement 4.3;
 * confirmed by grep: zero references outside its own model file, factory,
 * and test — no hasRole/hasAnyRole/visible/hidden/authorize/canX gate
 * exists because there is no call site at all yet).
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 24 existing Policies (mirrors PrestamoPolicy's shape per
 * design D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any call site — this Policy is additive-only in
 * this change (Slice 5b-catalog).
 */
class ConsultaAsistentePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_consulta_asistente');
    }

    public function view(User $user, ConsultaAsistente $consultaAsistente): bool
    {
        return $user->can('view_consulta_asistente');
    }

    public function create(User $user): bool
    {
        return $user->can('create_consulta_asistente');
    }

    public function update(User $user, ConsultaAsistente $consultaAsistente): bool
    {
        return $user->can('update_consulta_asistente');
    }

    public function delete(User $user, ConsultaAsistente $consultaAsistente): bool
    {
        return $user->can('delete_consulta_asistente');
    }
}
