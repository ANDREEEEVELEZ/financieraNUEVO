<?php

namespace App\Http\Concerns;

use App\Models\Asesor;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Resolves the Asesor record linked to an Asesor-role user, failing closed
 * with an explicit 403 (through the ApiResponse envelope) when no linked
 * Asesor record exists, instead of silently falling through to an
 * unrestricted query.
 */
trait AsesorScopeGuard
{
    /**
     * @throws AuthorizationException when the user has no linked Asesor record.
     */
    protected function resolveAsesorOrAbort(User $user): Asesor
    {
        $asesor = $user->asesor;

        if (! $asesor) {
            throw new AuthorizationException(
                'Cuenta de asesor mal configurada: sin asesor vinculado.',
                403
            );
        }

        return $asesor;
    }
}
