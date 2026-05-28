<?php

use App\Contracts\CacheServiceInterface;
use App\Contracts\NotificationServiceInterface;
use App\Models\Prestamo;
use App\Models\User;
use App\Observers\PrestamoObserver;
use Illuminate\Support\Collection;

uses(Tests\TestCase::class);

afterEach(fn() => \Mockery::close());

// ── T-2.3: created() uses cache->getSupervisores(), not User::role() ─────────

it('created() calls cache->getSupervisores() and not User::role() directly', function () {
    $mockCache = \Mockery::mock(CacheServiceInterface::class);
    $mockNotifications = \Mockery::mock(NotificationServiceInterface::class);

    $supervisor = \Mockery::mock(User::class)->makePartial();
    $supervisor->shouldReceive('getAttribute')->with('id')->andReturn(99);

    $mockCache->shouldReceive('invalidateStatsCache')->once();
    $mockCache->shouldReceive('getSupervisores')->once()->andReturn(collect([$supervisor]));
    $mockNotifications->shouldReceive('invalidateNotificationsCache')->once()->with(99);

    $observer = new PrestamoObserver($mockCache, $mockNotifications);

    // Use makePartial so property assignment works
    $prestamo = \Mockery::mock(Prestamo::class)->makePartial();
    $prestamo->shouldReceive('getAttribute')->with('id')->andReturn(1);

    $observer->created($prestamo);

    // Mockery verifies expectations on teardown
    expect(true)->toBeTrue();
});

// ── T-2.3: updated() calls cache->getSupervisores() when estado changes ──────

it('updated() calls cache->getSupervisores() when estado changes', function () {
    $mockCache = \Mockery::mock(CacheServiceInterface::class);
    $mockNotifications = \Mockery::mock(NotificationServiceInterface::class);

    $supervisor = \Mockery::mock(User::class)->makePartial();
    $supervisor->shouldReceive('getAttribute')->with('id')->andReturn(99);

    $mockCache->shouldReceive('invalidateStatsCache')->once();
    $mockCache->shouldReceive('getSupervisores')->once()->andReturn(collect([$supervisor]));
    $mockNotifications->shouldReceive('invalidateNotificationsCache')->with(\Mockery::any())->zeroOrMoreTimes();

    $observer = new PrestamoObserver($mockCache, $mockNotifications);

    $prestamo = \Mockery::mock(Prestamo::class)->makePartial();
    $prestamo->shouldReceive('getAttribute')->with('id')->andReturn(1);
    $prestamo->shouldReceive('wasChanged')->with('estado')->andReturn(true);
    $prestamo->shouldReceive('getAttribute')->with('estado')->andReturn('Pendiente');
    $prestamo->shouldReceive('getAttribute')->with('grupo')->andReturn(null);

    $observer->updated($prestamo);

    expect(true)->toBeTrue();
});

// ── Triangulation: getSupervisores called once per event, empty collection OK ─

it('created() tolerates empty supervisor collection without error', function () {
    $mockCache = \Mockery::mock(CacheServiceInterface::class);
    $mockNotifications = \Mockery::mock(NotificationServiceInterface::class);

    $mockCache->shouldReceive('invalidateStatsCache')->once();
    $mockCache->shouldReceive('getSupervisores')->once()->andReturn(collect());
    // No notifications should be sent — empty supervisor list
    $mockNotifications->shouldReceive('invalidateNotificationsCache')->never();

    $observer = new PrestamoObserver($mockCache, $mockNotifications);

    $prestamo = \Mockery::mock(Prestamo::class)->makePartial();
    $prestamo->shouldReceive('getAttribute')->with('id')->andReturn(2);

    $observer->created($prestamo);

    expect(true)->toBeTrue();
});
