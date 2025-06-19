<?php

use App\Models\Asesor;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Modelo Asesor', function () {
    it('Verifica si se puede crear un asesor', function () {
        $asesor = Asesor::factory()->create();
        expect($asesor)->toBeInstanceOf(Asesor::class);
        expect($asesor->persona)->not()->toBeNull();
        expect($asesor->user)->not()->toBeNull();
        expect($asesor->codigo_asesor)->toStartWith('ASR-');
    });

    it('Verifica si se puede registrar un nuevo asesor y asociarlo a persona y usuario', function () {
        $asesor = Asesor::factory()->create();
        expect($asesor->persona)->toBeInstanceOf(Persona::class);
        expect($asesor->user)->toBeInstanceOf(User::class);
    });

    it('Verifica si se puede modificar los datos del asesor', function () {
        $asesor = Asesor::factory()->create(['estado_asesor' => 'activo']);
        $asesor->estado_asesor = 'inactivo';
        $asesor->save();
        $asesor->refresh();
        expect($asesor->estado_asesor)->toBe('inactivo');
    });

    it('Verifica si se puede eliminar lógicamente un asesor', function () {
        $asesor = Asesor::factory()->create();
        $id = $asesor->id;
        $asesor->delete();
        expect(Asesor::find($id))->toBeNull();
    });

    it('Verifica si la relación persona funciona', function () {
        $asesor = Asesor::factory()->create();
        expect($asesor->persona)->toBeInstanceOf(Persona::class);
    });

    it('Verifica si la relación user funciona', function () {
        $asesor = Asesor::factory()->create();
        expect($asesor->user)->toBeInstanceOf(User::class);
    });
});
