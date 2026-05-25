<?php

it('session http_only is true', function () {
    expect((bool) config('session.http_only'))->toBeTrue();
});

it('session same_site is strict', function () {
    expect(config('session.same_site'))->toBe('strict');
});

it('session lifetime default is 30 minutes', function () {
    expect(file_get_contents(base_path('config/session.php')))
        ->toContain("SESSION_LIFETIME', 30");
});

it('session secure cookie defaults to true in config file', function () {
    expect(file_get_contents(base_path('config/session.php')))
        ->toContain("SESSION_SECURE_COOKIE', true");
});

it('session encrypt defaults to true in config file', function () {
    expect(file_get_contents(base_path('config/session.php')))
        ->toContain("SESSION_ENCRYPT', true");
});
