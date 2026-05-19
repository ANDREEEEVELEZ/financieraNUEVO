<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * PR-1 | Task 1.1 – P0 Backdoor Routes must not exist.
 * All of these routes expose unauthenticated attack surfaces and MUST return 404.
 */
class P0RoutesTest extends TestCase
{
    public function test_force_auth_route_does_not_exist(): void
    {
        $response = $this->get('/force-auth/test@example.com');

        $response->assertStatus(404);
    }

    public function test_generar_esquema_route_does_not_exist(): void
    {
        $response = $this->get('/generar-esquema');

        $response->assertStatus(404);
    }

    public function test_fix_routes_route_does_not_exist(): void
    {
        $response = $this->get('/fix-routes');

        $response->assertStatus(404);
    }

    public function test_check_filament_route_does_not_exist(): void
    {
        $response = $this->get('/check-filament');

        $response->assertStatus(404);
    }

    public function test_force_logout_route_does_not_exist(): void
    {
        $response = $this->get('/force-logout');

        $response->assertStatus(404);
    }

    public function test_auth_check_route_does_not_exist(): void
    {
        $response = $this->get('/auth-check');

        $response->assertStatus(404);
    }
}
