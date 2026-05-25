<?php

use App\Http\Requests\Auth\AuthRequest;
use App\Http\Requests\Cliente\UpdateClienteRequest;
use App\Http\Requests\Prestamo\UpdatePrestamoRequest;

it('AuthRequest class exists', function () {
    expect(class_exists(AuthRequest::class))->toBeTrue();
});

it('AuthRequest validates email and password', function () {
    $rules = (new AuthRequest())->rules();
    expect($rules)->toHaveKey('email')->toHaveKey('password');
});

it('UpdatePrestamoRequest class exists', function () {
    expect(class_exists(UpdatePrestamoRequest::class))->toBeTrue();
});

it('UpdatePrestamoRequest validates estado', function () {
    $rules = (new UpdatePrestamoRequest())->rules();
    expect($rules)->toHaveKey('estado');
});

it('UpdateClienteRequest class exists', function () {
    expect(class_exists(UpdateClienteRequest::class))->toBeTrue();
});
