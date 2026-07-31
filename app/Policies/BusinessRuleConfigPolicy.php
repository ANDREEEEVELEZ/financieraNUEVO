<?php

namespace App\Policies;

use App\Models\BusinessRuleConfig;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * BusinessRuleConfig has no Filament UI at all — it is read exclusively by
 * `BusinessRuleService` (`::where('clave', $clave)->first()`), with ZERO
 * authorization check anywhere (design D7 / spec Requirement 4.3; confirmed
 * by grep: its only consumer is a plain attribute read, never a
 * hasRole/hasAnyRole/visible/hidden/authorize/canX gate).
 *
 * Parity-preserving choice: Shield-style CRUD permission checks, consistent
 * with the other 21 existing Policies (mirrors PrestamoPolicy's shape per
 * design D7's instruction for models with no inline check). PermissionSeeder
 * grants every permission below to ALL FOUR existing roles, so this is
 * functionally equivalent to "allow any authenticated user" — the current
 * de-facto behavior — while staying idiomatically consistent with the
 * codebase (Requirement 4.3 forbids silently tightening access).
 *
 * Not yet referenced by any call site — this Policy is additive-only in
 * this change (Slice 5b-catalog).
 */
class BusinessRuleConfigPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_business_rule_config');
    }

    public function view(User $user, BusinessRuleConfig $businessRuleConfig): bool
    {
        return $user->can('view_business_rule_config');
    }

    public function create(User $user): bool
    {
        return $user->can('create_business_rule_config');
    }

    public function update(User $user, BusinessRuleConfig $businessRuleConfig): bool
    {
        return $user->can('update_business_rule_config');
    }

    public function delete(User $user, BusinessRuleConfig $businessRuleConfig): bool
    {
        return $user->can('delete_business_rule_config');
    }
}
