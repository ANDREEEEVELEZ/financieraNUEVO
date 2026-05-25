<?php

use App\Filament\Dashboard\Pages\Dashboard;
use App\Filament\Dashboard\Widgets\CuotasVigentesWidget;
use App\Filament\Dashboard\Widgets\KPIAdminWidget;
use App\Filament\Dashboard\Widgets\KPIAsesorWidget;
use App\Filament\Dashboard\Widgets\KPIJefeCreditosWidget;
use App\Filament\Dashboard\Widgets\KPIJefeOperacionesWidget;
use App\Filament\Dashboard\Resources\EgresosResource\Widgets\EgresosStatsWidget;
use App\Filament\Dashboard\Resources\IngresosResource\Widgets\IngresosStatsWidget;
use App\Filament\Dashboard\Resources\PagoResource\Widgets\PagosStatsWidget;
use App\Models\Asesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
    }
    Cache::flush();
});

it('routes Asesor role to KPIAsesorWidget + CuotasVigentes + PagosStats', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Asesor');
    Asesor::factory()->create(['user_id' => $user->id, 'estado_asesor' => 'activo']);

    $this->actingAs($user);
    $widgets = (new Dashboard())->getWidgets();

    expect($widgets)->toBe([
        KPIAsesorWidget::class,
        CuotasVigentesWidget::class,
        PagosStatsWidget::class,
    ]);
});

it('routes Jefe de operaciones role to KPIJefeOperacionesWidget + full widget set', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Jefe de operaciones');

    $this->actingAs($user);
    $widgets = (new Dashboard())->getWidgets();

    expect($widgets)->toBe([
        KPIJefeOperacionesWidget::class,
        CuotasVigentesWidget::class,
        PagosStatsWidget::class,
        IngresosStatsWidget::class,
        EgresosStatsWidget::class,
    ]);
});

it('routes Jefe de creditos role to KPIJefeCreditosWidget + CuotasVigentes + PagosStats', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('Jefe de creditos');

    $this->actingAs($user);
    $widgets = (new Dashboard())->getWidgets();

    expect($widgets)->toBe([
        KPIJefeCreditosWidget::class,
        CuotasVigentesWidget::class,
        PagosStatsWidget::class,
    ]);
});

it('routes super_admin (no specific role match) to KPIAdminWidget + full widget set', function () {
    $user = User::factory()->create(['active' => true]);
    $user->assignRole('super_admin');

    $this->actingAs($user);
    $widgets = (new Dashboard())->getWidgets();

    expect($widgets)->toBe([
        KPIAdminWidget::class,
        CuotasVigentesWidget::class,
        PagosStatsWidget::class,
        IngresosStatsWidget::class,
        EgresosStatsWidget::class,
    ]);
});

it('CuotasVigentesWidget declares $isLazy = true', function () {
    $isLazy = (new ReflectionClass(CuotasVigentesWidget::class))
        ->getStaticPropertyValue('isLazy');

    expect($isLazy)->toBeTrue();
});

it('all KPI and CuotasVigentes widgets declare $isLazy = true', function ($widgetClass) {
    $isLazy = (new ReflectionClass($widgetClass))
        ->getStaticPropertyValue('isLazy');

    expect($isLazy)->toBeTrue();
})->with([
    'KPIAsesorWidget'          => KPIAsesorWidget::class,
    'KPIJefeOperacionesWidget' => KPIJefeOperacionesWidget::class,
    'KPIJefeCreditosWidget'    => KPIJefeCreditosWidget::class,
    'KPIAdminWidget'           => KPIAdminWidget::class,
    'CuotasVigentesWidget'     => CuotasVigentesWidget::class,
]);
