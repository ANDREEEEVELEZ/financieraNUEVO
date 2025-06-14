<?php

use App\Models\Asesor;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Modelo Asesor', function () {
    it('puede crear un asesor', function () {
        $asesor = Asesor::factory()->create();
        expect($asesor)->toBeInstanceOf(Asesor::class);
        expect($asesor->persona)->not()->toBeNull();
        expect($asesor->user)->not()->toBeNull();
        expect($asesor->codigo_asesor)->toStartWith('ASR-');
    });

    it('puede actualizar un asesor', function () {
        $asesor = Asesor::factory()->create();
        $asesor->estado_asesor = 'inactivo';
        $asesor->save();
        $asesor->refresh();
        expect($asesor->estado_asesor)->toBe('inactivo');
    });

    it('puede eliminar un asesor', function () {
        $asesor = Asesor::factory()->create();
        $asesorId = $asesor->id;
        $asesor->delete();
        expect(Asesor::find($asesorId))->toBeNull();
    });

    it('relación persona funciona', function () {
        $asesor = Asesor::factory()->create();
        expect($asesor->persona)->toBeInstanceOf(Persona::class);
    });

    it('relación user funciona', function () {
        $asesor = Asesor::factory()->create();
        expect($asesor->user)->toBeInstanceOf(User::class);
    });
});
