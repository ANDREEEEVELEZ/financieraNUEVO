<?php

use App\Http\Resources\Api\ProductoFinancieroResource;
use App\Models\ProductoFinanciero;
use Illuminate\Http\Request;

uses(\Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Resolve a ProductoFinancieroResource to its array representation.
 */
function productoResourceResolve(ProductoFinanciero $producto): array
{
    $resource = new ProductoFinancieroResource($producto);
    $request  = Request::create('/api/productos', 'GET');

    // Use response()->getData(true) to get fully serialized array with nested resources.
    $data = $resource->response($request)->getData(true);

    return $data['data'];
}

it('includes all 14 required fields', function () {
    $producto = ProductoFinanciero::factory()->create([
        'codigo'                   => 'PROD-001',
        'nombre'                   => 'Prestamo Grupal',
        'tipo'                     => 'grupal',
        'tasa_interes'             => '0.1500',
        'tasa_mora'                => '0.0200',
        'permite_condonacion_mora' => false,
        'permite_retanqueo'        => true,
        'penalizacion_separacion'  => '0.0500',
        'monto_minimo'             => '500.00',
        'monto_maximo'             => '10000.00',
        'plazo_minimo_meses'       => 3,
        'plazo_maximo_meses'       => 24,
        'activo'                   => true,
    ]);

    $data = productoResourceResolve($producto);

    expect($data)->toHaveKey('id');
    expect($data)->toHaveKey('codigo');
    expect($data)->toHaveKey('nombre');
    expect($data)->toHaveKey('tipo');
    expect($data)->toHaveKey('tasa_interes');
    expect($data)->toHaveKey('tasa_mora');
    expect($data)->toHaveKey('permite_condonacion_mora');
    expect($data)->toHaveKey('permite_retanqueo');
    expect($data)->toHaveKey('penalizacion_separacion');
    expect($data)->toHaveKey('monto_minimo');
    expect($data)->toHaveKey('monto_maximo');
    expect($data)->toHaveKey('plazo_minimo_meses');
    expect($data)->toHaveKey('plazo_maximo_meses');
    expect($data)->toHaveKey('activo');
});

it('does NOT include config_json in response', function () {
    $producto = ProductoFinanciero::factory()->create([
        'config_json' => ['tasa_especial' => 0.05, 'visible' => true],
    ]);

    $data = productoResourceResolve($producto);

    expect(array_key_exists('config_json', $data))->toBeFalse();
});

it('activo is boolean', function () {
    $producto = ProductoFinanciero::factory()->create(['activo' => true]);

    $data = productoResourceResolve($producto);

    expect($data['activo'])->toBeBool();
    expect($data['activo'])->toBeTrue();
});

it('activo is false when product is inactive', function () {
    $producto = ProductoFinanciero::factory()->create(['activo' => false]);

    $data = productoResourceResolve($producto);

    expect($data['activo'])->toBeFalse();
});
