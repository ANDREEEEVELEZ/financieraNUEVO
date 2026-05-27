<?php

use App\Http\Resources\Api\AsesorResource;
use App\Models\Asesor;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Http\Request;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Resolve AsesorResource as a given authenticated user.
 * Uses response()->getData(true)['data'] so nested resources are fully serialized.
 */
function asesorResourceAs(User $viewer, Asesor $asesor): array
{
    auth()->login($viewer);

    $resource = new AsesorResource($asesor);
    $request  = Request::create('/api/asesores', 'GET');
    $request->setUserResolver(fn () => $viewer);

    $responseData = $resource->response($request)->getData(true);

    return $responseData['data'];
}

it('includes required shape fields', function () {
    $user   = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);

    $data = asesorResourceAs($user, $asesor);

    expect($data)->toHaveKey('id');
    expect($data)->toHaveKey('codigo_asesor');
    expect($data)->toHaveKey('fecha_ingreso');
    expect($data)->toHaveKey('estado_asesor');
});

it('does not expose user_id (AS-8)', function () {
    $user   = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);

    $data = asesorResourceAs($user, $asesor);

    expect($data)->not->toHaveKey('user_id');
});

it('omits persona key when relation is not loaded', function () {
    $user   = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    // Create without eager-loading persona
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);
    // Ensure persona is NOT loaded on the model instance
    $freshAsesor = Asesor::find($asesor->id);

    $data = asesorResourceAs($user, $freshAsesor);

    // whenLoaded returns MissingValue when not loaded — key is absent
    expect($data)->not->toHaveKey('persona');
});

it('includes nested persona when relation is loaded', function () {
    $user   = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);

    // Eager-load the persona relation
    $asesorWithPersona = Asesor::with('persona')->find($asesor->id);

    $data = asesorResourceAs($user, $asesorWithPersona);

    expect($data)->toHaveKey('persona');
    expect($data['persona'])->toBeArray();
    expect($data['persona'])->toHaveKey('id');
    expect($data['persona'])->toHaveKey('nombre');
    expect($data['persona'])->toHaveKey('apellidos');
});

it('nested persona hides PII for Asesor role (AS-9)', function () {
    $user   = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    $asesor = Asesor::factory()->create(['user_id' => $user->id]);

    $asesorWithPersona = Asesor::with('persona')->find($asesor->id);

    $data = asesorResourceAs($user, $asesorWithPersona);

    expect($data['persona'])->not->toHaveKey('DNI');
    expect($data['persona'])->not->toHaveKey('celular');
    expect($data['persona'])->not->toHaveKey('correo');
    expect($data['persona'])->not->toHaveKey('direccion');
});

it('nested persona exposes PII for super_admin role (AS-9)', function () {
    $admin  = User::factory()->create(['active' => true]);
    $admin->assignRole('super_admin');
    $asesor = Asesor::factory()->create();

    $asesorWithPersona = Asesor::with('persona')->find($asesor->id);

    $data = asesorResourceAs($admin, $asesorWithPersona);

    expect($data['persona'])->toHaveKey('DNI');
    expect($data['persona'])->toHaveKey('celular');
    expect($data['persona'])->toHaveKey('correo');
    expect($data['persona'])->toHaveKey('direccion');
});
