<?php

declare(strict_types=1);

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\ClienteScoring;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('scoringVigente returns the vigente scoring record', function () {
    $asesor  = Asesor::factory()->create();
    $cliente = Cliente::factory()->create(['asesor_id' => $asesor->id]);

    $vigente    = ClienteScoring::create(['cliente_id' => $cliente->id, 'score' => 80, 'grado' => 'A', 'vigente' => true]);
    $noVigente  = ClienteScoring::create(['cliente_id' => $cliente->id, 'score' => 50, 'grado' => 'C', 'vigente' => false]);

    $result = $cliente->scoringVigente;

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($vigente->id);
});

it('scoringVigente returns null when no vigente scoring exists', function () {
    $asesor  = Asesor::factory()->create();
    $cliente = Cliente::factory()->create(['asesor_id' => $asesor->id]);

    ClienteScoring::create(['cliente_id' => $cliente->id, 'score' => 50, 'grado' => 'C', 'vigente' => false]);

    expect($cliente->scoringVigente)->toBeNull();
});
