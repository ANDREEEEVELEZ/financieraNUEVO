<?php

use App\Models\Prestamo;
use App\Models\Grupo;
use App\Models\CuotasGrupales;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

describe('Modelo Prestamo', function () {
    it('puede crear un préstamo y asociarlo a un grupo', function () {
        $grupo = Grupo::factory()->create();
        $prestamo = Prestamo::factory()->create(['grupo_id' => $grupo->id]);
        expect($prestamo->grupo)->toBeInstanceOf(Grupo::class);
    });

    it('puede tener cuotas grupales asociadas', function () {
        $prestamo = Prestamo::factory()->create();
        $cuota = CuotasGrupales::factory()->create(['prestamo_id' => $prestamo->id]);
        expect($prestamo->cuotasGrupales->first())->toBeInstanceOf(CuotasGrupales::class);
    });
});
