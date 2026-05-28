<?php

use App\Filament\Dashboard\Resources\PrestamoResource;

uses(Tests\TestCase::class);

// ── T-2.9: grupo filter does not use PHP-side filter (N+1 pattern) ───────────

it('PrestamoResource form() grupo options closure does not use ->filter() with per-grupo subqueries', function () {
    $resourceCode = file_get_contents(app_path('Filament/Dashboard/Resources/PrestamoResource.php'));

    // The old N+1 pattern: fetches all grupos then filters in PHP with a subquery per grupo
    // This must be replaced with a single DB-level whereDoesntHave query
    expect($resourceCode)->not->toContain(
        '->filter(function ($grupo)'
    );
});

// ── T-2.9: grupo filter uses whereDoesntHave (DB-level) ───────────────────────

it('PrestamoResource form() grupo options closure uses whereDoesntHave', function () {
    $resourceCode = file_get_contents(app_path('Filament/Dashboard/Resources/PrestamoResource.php'));

    expect($resourceCode)->toContain('whereDoesntHave(');
});
