<?php

use App\Models\Asesor;
use App\Models\User;
use App\Infrastructure\Cache\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('AsesorObserver', function () {
    beforeEach(function () {
        foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    });

    it('invalida la cache del usuario cuando el estado_asesor cambia', function () {
        $user   = User::factory()->create(['active' => true]);
        $asesor = Asesor::factory()->create(['user_id' => $user->id, 'estado_asesor' => 'ACTIVO']);

        $cacheKey = 'ec_asesor_user_' . $user->id;
        Cache::put($cacheKey, $asesor, 300);

        expect(Cache::has($cacheKey))->toBeTrue();

        $asesor->estado_asesor = 'INACTIVO';
        $asesor->save();

        expect(Cache::has($cacheKey))->toBeFalse(
            'La cache del usuario debe invalidarse cuando el estado del asesor cambia'
        );
    });

    it('no rompe si el asesor no tiene user_id', function () {
        $asesor = Asesor::factory()->create(['user_id' => null]);

        // No debe lanzar excepción
        expect(fn () => $asesor->update(['estado_asesor' => 'INACTIVO']))
            ->not->toThrow(\Throwable::class);
    });

    it('no manipula la sesión HTTP — solo invalida cache', function () {
        // Observer must never call Auth::logout() or Session::invalidate()
        // We verify by checking the observer class has no Auth/Session imports
        $observerCode = file_get_contents(app_path('Observers/AsesorObserver.php'));

        expect($observerCode)
            ->not->toContain('Auth::logout')
            ->not->toContain('Session::invalidate')
            ->not->toContain('Session::regenerateToken');
    });
});

describe('PersonaObserver', function () {
    it('no manipula la sesión HTTP — solo invalida cache', function () {
        $observerCode = file_get_contents(app_path('Observers/PersonaObserver.php'));

        expect($observerCode)
            ->not->toContain('Auth::logout')
            ->not->toContain('Session::invalidate')
            ->not->toContain('Session::regenerateToken');
    });
});
