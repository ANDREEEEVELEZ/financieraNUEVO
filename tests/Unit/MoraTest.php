<?php

use App\Models\Mora;
use App\Models\CuotasGrupales;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Modelo Mora', function () {
    it('puede crear una mora y asociarla a una cuota grupal', function () {
        $cuota = CuotasGrupales::factory()->create();
        $mora = Mora::factory()->create(['cuota_grupal_id' => $cuota->id]);
        expect($mora->cuotaGrupal)->toBeInstanceOf(CuotasGrupales::class);
    });

    it('calcula el monto de mora dinámicamente', function () {
        $cuota = CuotasGrupales::factory()->create();
        $mora = Mora::factory()->create(['cuota_grupal_id' => $cuota->id]);
        $monto = $mora->monto_mora_calculado;
        expect($monto)->not()->toBeNull();
        expect($monto)->toBeNumeric();
    });
});
