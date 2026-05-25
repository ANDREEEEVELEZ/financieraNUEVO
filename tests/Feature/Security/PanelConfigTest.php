<?php

it('Filament panel IDs are unique', function () {
    $ids = array_keys(\Filament\Facades\Filament::getPanels());
    expect($ids)->toHaveCount(count(array_unique($ids)));
});

it('admin panel is registered', function () {
    expect(\Filament\Facades\Filament::getPanels())->toHaveKey('admin');
});

it('dashboard panel is registered', function () {
    expect(\Filament\Facades\Filament::getPanels())->toHaveKey('dashboard');
});
