<?php

it('AsistenteVirtual page class does not exist', function () {
    expect(class_exists('App\\Filament\\Dashboard\\Pages\\AsistenteVirtual', false))->toBeFalse();
});

it('AsistenteController class does not exist', function () {
    expect(class_exists('App\\Http\\Controllers\\AsistenteController', false))->toBeFalse();
});

it('AsistenteVirtual.php file is deleted', function () {
    expect(app_path('Filament/Dashboard/Pages/AsistenteVirtual.php'))->not->toBeFile();
});

it('AsistenteController.php file is deleted', function () {
    expect(app_path('Http/Controllers/AsistenteController.php'))->not->toBeFile();
});
