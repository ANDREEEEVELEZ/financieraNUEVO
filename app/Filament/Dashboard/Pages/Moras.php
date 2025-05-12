<?php

namespace App\Filament\Dashboard\Pages;

use Filament\Pages\Page;
use App\Models\Mora;

class Moras extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';
    protected static string $view = 'filament.dashboard.pages.mora';
    protected static ?string $title = 'Gestión de Moras';


    protected static ?int $navigationSort = 5;

    public function getViewData(): array
    {
        return [
            'moras' => Mora::with('cuotaGrupal')->latest()->get(),
        ];
    }
}
