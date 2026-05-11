<?php

use App\Domain\Mora\MoraSemaforo;
use App\Models\BusinessRuleConfig;
use App\Models\ProductoFinanciero;
use App\Services\BusinessRuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── MoraSemaforo Value Object ───────────────────────────────────────────────

describe('MoraSemaforo', function () {

    it('clasifica 0 días como NINGUNA', function () {
        $semaforo = MoraSemaforo::clasificar(0);
        expect($semaforo->nivel)->toBe(MoraSemaforo::NINGUNA);
        expect($semaforo->estaAlDia())->toBeTrue();
        expect($semaforo->requiereRecuperacion())->toBeFalse();
    });

    it('clasifica 1–6 días como LEVE', function () {
        foreach ([1, 3, 6] as $dias) {
            $s = MoraSemaforo::clasificar($dias);
            expect($s->nivel)->toBe(MoraSemaforo::LEVE);
            expect($s->requiereAccion())->toBeTrue();
        }
    });

    it('clasifica 7–14 días como MEDIA (umbral por defecto)', function () {
        foreach ([7, 10, 14] as $dias) {
            $s = MoraSemaforo::clasificar($dias);
            expect($s->nivel)->toBe(MoraSemaforo::MEDIA);
        }
    });

    it('clasifica 15–29 días como CRITICA', function () {
        foreach ([15, 20, 29] as $dias) {
            $s = MoraSemaforo::clasificar($dias);
            expect($s->nivel)->toBe(MoraSemaforo::CRITICA);
        }
    });

    it('clasifica 30–59 días como GRAVE', function () {
        foreach ([30, 45, 59] as $dias) {
            $s = MoraSemaforo::clasificar($dias);
            expect($s->nivel)->toBe(MoraSemaforo::GRAVE);
        }
    });

    it('clasifica 60+ días como RECUPERACION', function () {
        foreach ([60, 90, 180] as $dias) {
            $s = MoraSemaforo::clasificar($dias);
            expect($s->nivel)->toBe(MoraSemaforo::RECUPERACION);
            expect($s->requiereRecuperacion())->toBeTrue();
        }
    });

    it('respeta umbrales personalizados inyectados', function () {
        // Súper Admin cambia umbral de recuperación a 90 días
        $s = MoraSemaforo::clasificar(75, umbralRecuperacion: 90);
        expect($s->nivel)->toBe(MoraSemaforo::GRAVE); // 75 < 90 → GRAVE, no RECUPERACION

        $s2 = MoraSemaforo::clasificar(95, umbralRecuperacion: 90);
        expect($s2->nivel)->toBe(MoraSemaforo::RECUPERACION);
    });

    it('retorna array con todos los campos necesarios para la UI', function () {
        $array = MoraSemaforo::clasificar(45)->toArray();
        expect($array)->toHaveKeys(['nivel', 'dias_atraso', 'color', 'icono', 'etiqueta']);
        expect($array['color'])->toBe('danger');
    });
});

// ─── BusinessRuleService ─────────────────────────────────────────────────────

describe('BusinessRuleService', function () {

    it('devuelve valores por defecto si no hay registros en BD', function () {
        $service = new BusinessRuleService();
        // Sin registros en BD, debe caer en los defaults hardcodeados
        expect($service->umbralMediaDias())->toBe(7);
        expect($service->umbralRecuperacionDias())->toBe(60);
        expect($service->cuotasParaSeparar())->toBe(1);
    });

    it('lee los umbrales desde la BD cuando existen', function () {
        BusinessRuleConfig::updateOrCreate(
            ['clave' => 'mora.umbral_recuperacion_dias'],
            ['valor' => '90', 'tipo' => 'integer', 'descripcion' => 'Test override', 'grupo' => 'mora']
        );

        // Limpiar caché para que lea de BD
        Cache::flush();

        $service = new BusinessRuleService();
        expect($service->umbralRecuperacionDias())->toBe(90);
    });

    it('clasifica mora usando umbrales de la BD', function () {
        BusinessRuleConfig::updateOrCreate(
            ['clave' => 'mora.umbral_media_dias'],
            ['valor' => '10', 'tipo' => 'integer', 'descripcion' => 'Test', 'grupo' => 'mora']
        );
        Cache::flush();

        $service = new BusinessRuleService();

        // Con umbral de media en 10 días, 8 días = LEVE (no MEDIA)
        $semaforo = $service->clasificarMora(8);
        expect($semaforo->nivel)->toBe(MoraSemaforo::LEVE);

        // 12 días ya es MEDIA
        $semaforo2 = $service->clasificarMora(12);
        expect($semaforo2->nivel)->toBe(MoraSemaforo::MEDIA);
    });
});

// ─── ProductoFinanciero.admiteRetanqueo() ─────────────────────────────────────

describe('ProductoFinanciero', function () {

    it('no admite retanqueo por defecto', function () {
        $producto = ProductoFinanciero::factory()->create(['permite_retanqueo' => false]);
        expect($producto->admiteRetanqueo())->toBeFalse();
    });

    it('admite retanqueo cuando el Súper Admin lo habilita', function () {
        $producto = ProductoFinanciero::factory()->create(['permite_retanqueo' => true]);
        expect($producto->admiteRetanqueo())->toBeTrue();
    });
});
