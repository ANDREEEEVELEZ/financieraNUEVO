<?php

it('has X-Frame-Options header on login page', function () {
    $this->get('/dashboard/login')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

it('has X-Content-Type-Options header', function () {
    $this->get('/dashboard/login')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('has Strict-Transport-Security header with max-age', function () {
    $response = $this->get('/dashboard/login');

    expect($response->headers->has('Strict-Transport-Security'))->toBeTrue()
        ->and($response->headers->get('Strict-Transport-Security'))
        ->toContain('max-age=31536000');
});

it('has Referrer-Policy header', function () {
    $this->get('/dashboard/login')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('CSP is enforced, not Report-Only', function () {
    $response = $this->get('/dashboard/login');

    expect($response->headers->has('Content-Security-Policy'))->toBeTrue()
        ->and($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

it('CSP contains required directives', function () {
    $csp = $this->get('/dashboard/login')->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain('script-src');
});

it('has Permissions-Policy header with camera and payment blocked', function () {
    $response = $this->get('/dashboard/login');
    $policy   = $response->headers->get('Permissions-Policy');

    expect($response->headers->has('Permissions-Policy'))->toBeTrue()
        ->and($policy)->toContain('camera=()')
        ->and($policy)->toContain('payment=()');
});
