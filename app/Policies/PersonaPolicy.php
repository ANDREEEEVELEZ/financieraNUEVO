<?php

namespace App\Policies;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Persona (the shared base record behind both Cliente and Asesor) has no
 * Filament Resource of its own — it is read/written inline by
 * `ClienteResource`'s Pages and `AsesorResource`'s Pages (`::where('DNI',
 * ...)->exists()`, `::whereRaw(...)`, `::create(...)`), with ZERO
 * authorization check anywhere (design D7 / spec Requirement 4.3; confirmed
 * by grep: every reference is a plain duplicate-check read or a create
 * call, never a hasRole/hasAnyRole/visible/hidden/authorize/canX gate).
 * Note: `Persona.DNI` itself stays plaintext per spec Requirement 3.3 — this
 * Policy is unrelated to that non-goal, it only governs CRUD authorization.
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 28 existing Policies (mirrors PrestamoPolicy's shape per
 * design D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any call site — this Policy is additive-only in
 * this change (Slice 5b-catalog).
 */
class PersonaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_persona');
    }

    public function view(User $user, Persona $persona): bool
    {
        return $user->can('view_persona');
    }

    public function create(User $user): bool
    {
        return $user->can('create_persona');
    }

    public function update(User $user, Persona $persona): bool
    {
        return $user->can('update_persona');
    }

    public function delete(User $user, Persona $persona): bool
    {
        return $user->can('delete_persona');
    }
}
