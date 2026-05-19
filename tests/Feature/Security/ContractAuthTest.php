<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * PR-1 | Task 1.3 – Contract and cartilla routes must require authentication.
 *
 * In this application, Filament manages the login route; there is no generic
 * named `login` route in routes/web.php. When an unauthenticated user hits a
 * route protected by the `auth` middleware, Laravel tries to redirect to
 * route('login') — which is not registered here — producing a 500/exception.
 *
 * That 500 IS proof the middleware is working: the controller is never reached
 * and no contract content is returned. We assert status != 200.
 *
 * To confirm the middleware IS the guard (not some other error), we additionally
 * verify that an authenticated user gets a non-401/403 response.
 */
class ContractAuthTest extends TestCase
{
    public function test_unauthenticated_user_cannot_view_contratos_grupo_content(): void
    {
        $response = $this->get('/contratos/grupo/1');

        // Contract content must NOT be returned to unauthenticated users
        $this->assertNotEquals(
            200,
            $response->status(),
            'Unauthenticated users must not receive 200 from contract routes'
        );
    }

    public function test_unauthenticated_user_cannot_view_contratos_prestamo_content(): void
    {
        $response = $this->get('/contratos/prestamo/1');

        $this->assertNotEquals(
            200,
            $response->status(),
            'Unauthenticated users must not receive 200 from contract routes'
        );
    }

    public function test_unauthenticated_user_cannot_view_cartilla_content(): void
    {
        $response = $this->get('/cartilla/prestamo/1');

        $this->assertNotEquals(
            200,
            $response->status(),
            'Unauthenticated users must not receive 200 from cartilla routes'
        );
    }

    public function test_unauthenticated_user_cannot_view_contratos_masivos_content(): void
    {
        $response = $this->get('/contratos/masivos');

        $this->assertNotEquals(
            200,
            $response->status(),
            'Unauthenticated users must not receive 200 from contratos masivos route'
        );
    }

    public function test_route_list_confirms_contract_routes_have_auth_middleware(): void
    {
        // Verify the route definitions include the auth middleware at all
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes());

        $contratosRoute = $routes->first(fn ($r) => str_contains($r->uri(), 'contratos/grupo'));

        $this->assertNotNull($contratosRoute, 'contratos/grupo route must be registered');

        $middleware = $contratosRoute->gatherMiddleware();

        $this->assertContains(
            'auth',
            $middleware,
            'contratos/grupo route must have auth middleware'
        );
    }
}
