<?php

use App\Http\Resources\Api\PersonaResource;
use App\Http\Resources\Api\UserResource;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Http\Request;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Helper: build a PersonaResource array as the given authenticated user would see it.
 */
function personaResourceAs(User $viewer, Persona $persona): array
{
    // Authenticate the viewer so auth()->user() resolves inside the resource.
    auth()->login($viewer);

    $resource = new PersonaResource($persona);
    $request  = Request::create('/api/clientes', 'GET');
    $request->setUserResolver(fn () => $viewer);

    return $resource->resolve($request);
}

it('hides PII fields from Asesor role', function () {
    $asesor = User::factory()->create(['active' => true]);
    $asesor->assignRole('Asesor');

    $persona = Persona::factory()->create([
        'DNI'      => '12345678',
        'celular'  => '999999999',
        'correo'   => 'test@example.com',
        'direccion' => 'Calle Falsa 123',
    ]);

    $data = personaResourceAs($asesor, $persona);

    expect($data)->not->toHaveKey('DNI');
    expect($data)->not->toHaveKey('celular');
    expect($data)->not->toHaveKey('correo');
    expect($data)->not->toHaveKey('direccion');
});

it('exposes PII fields to super_admin role', function () {
    $admin = User::factory()->create(['active' => true]);
    $admin->assignRole('super_admin');

    $persona = Persona::factory()->create([
        'DNI'      => '87654321',
        'celular'  => '988888888',
        'correo'   => 'admin@example.com',
        'direccion' => 'Av. Principal 456',
    ]);

    $data = personaResourceAs($admin, $persona);

    expect($data)->toHaveKey('DNI');
    expect($data)->toHaveKey('celular');
    expect($data)->toHaveKey('correo');
    expect($data)->toHaveKey('direccion');
});

it('exposes PII fields to Jefe de creditos role', function () {
    $jc = User::factory()->create(['active' => true]);
    $jc->assignRole('Jefe de creditos');

    $persona = Persona::factory()->create([
        'DNI'      => '11223344',
        'celular'  => '977777777',
        'correo'   => 'jc@example.com',
        'direccion' => 'Jr. Comercio 789',
    ]);

    $data = personaResourceAs($jc, $persona);

    expect($data)->toHaveKey('DNI');
    expect($data)->toHaveKey('celular');
    expect($data)->toHaveKey('correo');
    expect($data)->toHaveKey('direccion');
});

it('includes demographic fields for Asesor role', function () {
    $asesor = User::factory()->create(['active' => true]);
    $asesor->assignRole('Asesor');

    $persona = Persona::factory()->create([
        'sexo'             => 'Femenino',
        'fecha_nacimiento' => '1990-05-15',
        'estado_civil'     => 'Casado',
        'distrito'         => 'Miraflores',
    ]);

    $data = personaResourceAs($asesor, $persona);

    expect($data)->toHaveKey('sexo', 'Femenino');
    expect($data)->toHaveKey('fecha_nacimiento', '1990-05-15');
    expect($data)->toHaveKey('estado_civil', 'Casado');
    expect($data)->toHaveKey('distrito', 'MIRAFLORES');
});

it('PII gate still blocks DNI/celular/correo/direccion for Asesor with demographic fields present', function () {
    $asesor = User::factory()->create(['active' => true]);
    $asesor->assignRole('Asesor');

    $persona = Persona::factory()->create([
        'sexo'      => 'Masculino',
        'DNI'       => '12345678',
        'celular'   => '999000111',
        'correo'    => 'test@test.com',
        'direccion' => 'Calle Lima 1',
    ]);

    $data = personaResourceAs($asesor, $persona);

    expect($data)->toHaveKey('sexo');
    expect($data)->not->toHaveKey('DNI');
    expect($data)->not->toHaveKey('celular');
    expect($data)->not->toHaveKey('correo');
    expect($data)->not->toHaveKey('direccion');
});

it('includes demographic fields for SA role alongside PII fields', function () {
    $admin = User::factory()->create(['active' => true]);
    $admin->assignRole('super_admin');

    $persona = Persona::factory()->create([
        'sexo'             => 'Masculino',
        'fecha_nacimiento' => '1985-03-20',
        'estado_civil'     => 'Soltero',
        'distrito'         => 'San Isidro',
        'DNI'              => '87654321',
    ]);

    $data = personaResourceAs($admin, $persona);

    expect($data)->toHaveKey('sexo', 'Masculino');
    expect($data)->toHaveKey('fecha_nacimiento', '1985-03-20');
    expect($data)->toHaveKey('estado_civil', 'Soltero');
    expect($data)->toHaveKey('distrito', 'SAN ISIDRO');
    expect($data)->toHaveKey('DNI', '87654321');
});

it('UserResource never includes password or remember_token', function () {
    $user = User::factory()->create(['active' => true]);

    auth()->login($user);
    $resource = new UserResource($user);
    $request  = Request::create('/api/auth/login', 'POST');
    $request->setUserResolver(fn () => $user);

    $data = $resource->resolve($request);

    expect($data)->not->toHaveKey('password');
    expect($data)->not->toHaveKey('remember_token');
    expect($data)->toHaveKey('id');
    expect($data)->toHaveKey('email');
    expect($data)->toHaveKey('roles');
});
