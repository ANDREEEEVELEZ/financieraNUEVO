<?php

namespace Tests\Feature\Security;

use App\Models\Asesor;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckUserActiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }

    public function test_inactive_user_is_logged_out_and_session_invalidated(): void
    {
        $user = User::factory()->create(['active' => false]);
        $user->assignRole('Asesor');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/dashboard/login');
        $this->assertGuest();
    }

    public function test_active_user_passes_through(): void
    {
        $user = User::factory()->create(['active' => true]);
        $user->assignRole('Asesor');

        // Active user should not be redirected by CheckUserActive
        // (may still redirect to login for other reasons — we just confirm it's not a 302 to /dashboard/login from CheckUserActive)
        $response = $this->actingAs($user)->get('/dashboard');

        $this->assertNotEquals('/dashboard/login', $response->headers->get('Location'));
    }

    public function test_inactive_asesor_record_triggers_logout(): void
    {
        $user   = User::factory()->create(['active' => true]);
        $asesor = Asesor::factory()->create(['user_id' => $user->id, 'estado_asesor' => 'INACTIVO']);
        $user->assignRole('Asesor');

        // Prime the cache with the inactive asesor so CheckUserActive finds it
        Cache::put(
            'ec_asesor_user_' . $user->id,
            $asesor,
            300
        );

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/dashboard/login');
        $this->assertGuest();
    }

    public function test_json_request_from_inactive_user_returns_403(): void
    {
        $user = User::factory()->create(['active' => false]);
        $user->assignRole('Asesor');

        $response = $this->actingAs($user)
            ->getJson('/api/notifications');

        $response->assertStatus(403);
    }
}
