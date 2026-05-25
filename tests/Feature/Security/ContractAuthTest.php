<?php

use Illuminate\Support\Facades\Route;

it('unauthenticated user cannot access contratos grupo', function () {
    expect($this->get('/contratos/grupo/1')->status())->not->toBe(200);
});

it('unauthenticated user cannot access contratos prestamo', function () {
    expect($this->get('/contratos/prestamo/1')->status())->not->toBe(200);
});

it('unauthenticated user cannot access cartilla prestamo', function () {
    expect($this->get('/cartilla/prestamo/1')->status())->not->toBe(200);
});

it('unauthenticated user cannot access contratos masivos', function () {
    expect($this->get('/contratos/masivos')->status())->not->toBe(200);
});

it('contratos/grupo route has auth middleware', function () {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn($r) => str_contains($r->uri(), 'contratos/grupo'));

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('auth');
});
