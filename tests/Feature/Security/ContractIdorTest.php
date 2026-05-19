<?php

namespace Tests\Feature\Security;

use App\Models\Asesor;
use App\Models\Grupo;
use App\Models\User;
use App\Services\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * IDOR protection on contract routes.
 * An Asesor must not be able to access contracts that belong to another asesor's group.
 */
class ContractIdorTest extends TestCase
{
    use RefreshDatabase;

    private User $asesor1User;
    private User $asesor2User;
    private Asesor $asesor1;
    private Asesor $asesor2;
    private Grupo $grupoDeAsesor1;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        $this->asesor1User = User::factory()->create(['active' => true]);
        $this->asesor1     = Asesor::factory()->create([
            'user_id'      => $this->asesor1User->id,
            'estado_asesor' => 'Activo',
        ]);
        $this->asesor1User->assignRole('Asesor');

        $this->asesor2User = User::factory()->create(['active' => true]);
        $this->asesor2     = Asesor::factory()->create([
            'user_id'       => $this->asesor2User->id,
            'estado_asesor' => 'Activo',
        ]);
        $this->asesor2User->assignRole('Asesor');

        // Group belongs to asesor1
        $this->grupoDeAsesor1 = Grupo::factory()->create([
            'asesor_id' => $this->asesor1->id,
        ]);

        // Prime asesor cache so CheckUserActive + ContratoGrupoController use it
        Cache::put('ec_asesor_user_' . $this->asesor1User->id, $this->asesor1, 300);
        Cache::put('ec_asesor_user_' . $this->asesor2User->id, $this->asesor2, 300);
    }

    public function test_asesor_cannot_access_another_asesor_grupo_contracts(): void
    {
        // Asesor 2 tries to access a contract for asesor 1's group
        $response = $this->actingAs($this->asesor2User)
            ->get("/contratos/grupo/{$this->grupoDeAsesor1->id}");

        $response->assertForbidden();
    }

    public function test_asesor_can_access_own_grupo_contracts(): void
    {
        // Asesor 1 accesses their own group — should NOT be forbidden by ownership check
        // (will still fail with 404/500 due to missing loan — that's fine; 403 is what we guard against)
        $response = $this->actingAs($this->asesor1User)
            ->get("/contratos/grupo/{$this->grupoDeAsesor1->id}");

        $this->assertNotEquals(403, $response->status(), 'Owner asesor should not get 403');
    }

    public function test_super_admin_can_access_any_grupo_contracts(): void
    {
        $admin = User::factory()->create(['active' => true]);
        $admin->assignRole('super_admin');

        $response = $this->actingAs($admin)
            ->get("/contratos/grupo/{$this->grupoDeAsesor1->id}");

        $this->assertNotEquals(403, $response->status(), 'super_admin should not be blocked by ownership check');
    }

    public function test_asesor_cannot_access_another_asesor_prestamo_contracts(): void
    {
        $prestamo = \App\Models\Prestamo::factory()->create([
            'grupo_id' => $this->grupoDeAsesor1->id,
            'estado'   => \App\Models\Prestamo::ESTADO_ACTIVO,
        ]);

        $response = $this->actingAs($this->asesor2User)
            ->get("/contratos/prestamo/{$prestamo->id}");

        $response->assertForbidden();
    }

    public function test_asesor_cannot_access_another_asesor_cartilla(): void
    {
        $prestamo = \App\Models\Prestamo::factory()->create([
            'grupo_id' => $this->grupoDeAsesor1->id,
            'estado'   => \App\Models\Prestamo::ESTADO_ACTIVO,
        ]);

        $response = $this->actingAs($this->asesor2User)
            ->get("/cartilla/prestamo/{$prestamo->id}");

        $response->assertForbidden();
    }
}
