<?php

use App\Services\WidgetStatsService;

describe('WidgetStatsService', function () {
    describe('Admin Stats', function () {
        it('formats currency correctly', function () {
            $formatted = WidgetStatsService::formatCurrency(50000.00);
            expect($formatted)->toBe('S/ 50,000.00');
        });

        it('returns danger color when mora > 10%', function () {
            $color = WidgetStatsService::getMoraColor(15.5);
            expect($color)->toBe('danger');
        });

        it('returns warning color when mora <= 10%', function () {
            $color = WidgetStatsService::getMoraColor(5.5);
            expect($color)->toBe('warning');
        });
    });

    describe('Flow Stats', function () {
        it('returns up icon for positive flow', function () {
            $icon = WidgetStatsService::getFlowTrendIcon(12000.00);
            expect($icon)->toBe('heroicon-m-arrow-trending-up');
        });

        it('returns down icon for negative flow', function () {
            $icon = WidgetStatsService::getFlowTrendIcon(-5000.00);
            expect($icon)->toBe('heroicon-m-arrow-trending-down');
        });

        it('returns success color for positive flow', function () {
            $color = WidgetStatsService::getFlowColor(12000.00);
            expect($color)->toBe('success');
        });

        it('returns danger color for negative flow', function () {
            $color = WidgetStatsService::getFlowColor(-5000.00);
            expect($color)->toBe('danger');
        });

        it('returns success color for zero flow', function () {
            $color = WidgetStatsService::getFlowColor(0);
            expect($color)->toBe('success');
        });
    });
});
