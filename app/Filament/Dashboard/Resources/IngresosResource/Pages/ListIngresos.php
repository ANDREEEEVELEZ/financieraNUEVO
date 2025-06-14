<?php

namespace App\Filament\Dashboard\Resources\IngresosResource\Pages;

use App\Filament\Dashboard\Resources\IngresosResource;
use App\Filament\Dashboard\Resources\IngresosResource\Widgets\IngresosStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIngresos extends ListRecords
{
    protected static string $resource = IngresosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->icon('heroicon-o-plus-circle'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            IngresosStatsWidget::class,
        ];
    }
}
