<?php

use App\Domain\Cartera\WidgetStatsService;

// NOTE: Alias-mock delegation tests (Mockery::mock('alias:...')) require process
// isolation (->isolate()) which is only available in Pest 4+. This project uses
// Pest 3.8, so delegation is validated at the integration level via KPIWidgetsTest.
// The four delegation methods are trivial one-liner pass-throughs; pure helpers are tested below.

// Pure helper tests — no mocks needed

it('formatCurrency formats a float as S/ with 2 decimal places', function () {
    expect(WidgetStatsService::formatCurrency(50000.00))->toBe('S/ 50,000.00');
    expect(WidgetStatsService::formatCurrency(0.0))->toBe('S/ 0.00');
    expect(WidgetStatsService::formatCurrency(1234.5))->toBe('S/ 1,234.50');
});

it('getMoraColor returns danger when tasaMora > 10', function () {
    expect(WidgetStatsService::getMoraColor(10.1))->toBe('danger');
    expect(WidgetStatsService::getMoraColor(15.5))->toBe('danger');
});

it('getMoraColor returns warning when tasaMora <= 10', function () {
    expect(WidgetStatsService::getMoraColor(10.0))->toBe('warning');
    expect(WidgetStatsService::getMoraColor(5.5))->toBe('warning');
});

it('getFlowTrendIcon returns up icon for non-negative flow and down icon for negative flow', function () {
    expect(WidgetStatsService::getFlowTrendIcon(0.0))->toBe('heroicon-m-arrow-trending-up');
    expect(WidgetStatsService::getFlowTrendIcon(12000.0))->toBe('heroicon-m-arrow-trending-up');
    expect(WidgetStatsService::getFlowTrendIcon(-1.0))->toBe('heroicon-m-arrow-trending-down');
});

it('getFlowColor returns success for non-negative flow and danger for negative flow', function () {
    expect(WidgetStatsService::getFlowColor(0.0))->toBe('success');
    expect(WidgetStatsService::getFlowColor(12000.0))->toBe('success');
    expect(WidgetStatsService::getFlowColor(-5000.0))->toBe('danger');
});
