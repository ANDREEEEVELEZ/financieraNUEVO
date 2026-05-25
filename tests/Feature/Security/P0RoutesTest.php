<?php

it('force-auth backdoor route does not exist', function () {
    $this->get('/force-auth/test@example.com')->assertNotFound();
});

it('generar-esquema route does not exist', function () {
    $this->get('/generar-esquema')->assertNotFound();
});

it('fix-routes route does not exist', function () {
    $this->get('/fix-routes')->assertNotFound();
});

it('check-filament route does not exist', function () {
    $this->get('/check-filament')->assertNotFound();
});

it('force-logout route does not exist', function () {
    $this->get('/force-logout')->assertNotFound();
});

it('auth-check route does not exist', function () {
    $this->get('/auth-check')->assertNotFound();
});
