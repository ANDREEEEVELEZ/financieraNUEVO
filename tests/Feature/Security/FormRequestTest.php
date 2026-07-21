<?php

use App\Http\Requests\Auth\AuthRequest;

it('AuthRequest class exists', function () {
    expect(class_exists(AuthRequest::class))->toBeTrue();
});

it('AuthRequest validates email and password', function () {
    $rules = (new AuthRequest)->rules();
    expect($rules)->toHaveKey('email')->toHaveKey('password');
});
