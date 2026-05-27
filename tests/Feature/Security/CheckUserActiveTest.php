<?php

use App\Models\Asesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
    }
});

it('inactive user is redirected to login and session is destroyed', function () {
    $user = User::factory()->create(['active' => false]);
    $user->assignRole('Asesor');

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/dashboard/login');
    $this->assertGuest();
});

it('active user passes through CheckUserActive', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');

    $response = $this->actingAs($user)->get('/dashboard');

    expect($response->headers->get('Location'))->not->toBe('/dashboard/login');
});

it('asesor with INACTIVO estado triggers logout on next request', function () {
    $user   = User::factory()->create(['active' => true]);
    $asesor = Asesor::factory()->create(['user_id' => $user->id, 'estado_asesor' => 'INACTIVO']);
    $user->assignRole('Asesor');

    Cache::put('ec_asesor_user_' . $user->id, $asesor, 300);

    $this->actingAs($user)->get('/dashboard')->assertRedirect('/dashboard/login');
    $this->assertGuest();
});

it('inactive user gets 403 on JSON requests', function () {
    $user = User::factory()->create(['active' => false]);
    $user->assignRole('Asesor');

    $this->actingAs($user)->getJson('/api/grupos')->assertStatus(403);
});

it('active asesor with role reaches dashboard without login redirect', function () {
    $user   = User::factory()->create(['active' => true]);
    $asesor = Asesor::factory()->create(['user_id' => $user->id, 'estado_asesor' => 'activo']);
    $user->assignRole('Asesor');

    $response = $this->actingAs($user)->get('/dashboard');

    expect($response->headers->get('Location'))->not->toBe('/dashboard/login');
    expect(Auth::check())->toBeTrue();
});
