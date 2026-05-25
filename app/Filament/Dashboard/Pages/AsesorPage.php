<?php

namespace App\Filament\Dashboard\Pages;

use App\Models\MetricaDiariaAsesor;
use App\Services\CarteraService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class AsesorPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Mi Panel';
    protected static ?string $title = 'Panel del Asesor';
    protected static string $view = 'filament.dashboard.pages.asesor';
    protected static ?int $navigationSort = 1;

    public string $activeTab = 'hoy';
    public array $cartera = [];
    public float $cobradoHoy = 0.0;
    public int $cuotasHoyCount = 0;

    public function mount(CarteraService $carteraService): void
    {
        $user          = Auth::user();
        $this->cartera = $carteraService->resumen($user);

        $cuotasHoy           = $this->cartera['cuotas_hoy'] ?? collect();
        $this->cuotasHoyCount = $cuotasHoy->count();

        $this->cobradoHoy = (float) (MetricaDiariaAsesor::where('asesor_id', $user->id)
            ->whereDate('fecha', today())
            ->value('cobrado') ?? 0);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }
}
