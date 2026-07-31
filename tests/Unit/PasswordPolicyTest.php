<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

// Uses the base framework TestCase (not Tests\TestCase) because this suite
// only needs the app container booted (to read the AppServiceProvider-
// registered Password::defaults() rule) and never touches the database —
// Tests\TestCase::setUp() seeds roles via a DB write that these tests don't
// need.
uses(Illuminate\Foundation\Testing\TestCase::class);

describe('Global password policy (Password::defaults())', function () {
    it('rejects a weak password missing mixed case, numbers, and symbols (Scenario 2.1.a)', function () {
        $validator = Validator::make(
            ['password' => 'password'],
            ['password' => ['required', Password::defaults()]]
        );

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('password'))->toBeTrue();
    });

    it('accepts a strong, non-compromised password (Scenario 2.1.c)', function () {
        $validator = Validator::make(
            ['password' => 'Xk9$mQw2Lp7zR4'],
            ['password' => ['required', Password::defaults()]]
        );

        expect($validator->passes())->toBeTrue();
    });

    it('rejects a compromised password via uncompromised() outside local/testing (Scenario 2.1.b)', function () {
        $password = 'Password123!';
        $hash = strtoupper(sha1($password));
        $suffix = substr($hash, 5);

        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response($suffix.':37'),
        ]);

        $previousEnv = app()->environment();
        app()['env'] = 'production';

        try {
            $validator = Validator::make(
                ['password' => $password],
                ['password' => ['required', Password::defaults()]]
            );

            expect($validator->fails())->toBeTrue();
        } finally {
            app()['env'] = $previousEnv;
        }
    });
});
