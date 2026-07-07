<?php

use App\Http\Concerns\AsesorScopeGuard;
use App\Models\Asesor;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function asesorScopeGuardHost(): object
{
    return new class
    {
        use AsesorScopeGuard;

        public function call(User $user): Asesor
        {
            return $this->resolveAsesorOrAbort($user);
        }
    };
}

it('aborts with 403 through the ApiResponse envelope when Asesor has no linked Asesor record', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');

    $host = asesorScopeGuardHost();

    try {
        $host->call($user);
        $this->fail('Expected AuthorizationException to be thrown.');
    } catch (AuthorizationException $e) {
        expect($e->getCode())->toBe(403);
    }
});

it('returns the linked Asesor when Asesor role user has one', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);

    $host = asesorScopeGuardHost();

    $resolved = $host->call($user);

    expect($resolved->id)->toBe($asesor->id);
});

it('returns null-safe behavior is not applicable to non-Asesor roles (guard is Asesor-specific)', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('super_admin');

    $host = asesorScopeGuardHost();

    // Non-Asesor roles should not be forced through this guard by callers;
    // but if invoked directly without a linked Asesor, it must still fail closed
    // rather than silently returning null (fail-closed contract for the trait itself).
    try {
        $host->call($user);
        $this->fail('Expected AuthorizationException to be thrown.');
    } catch (AuthorizationException $e) {
        expect($e->getCode())->toBe(403);
    }
});
