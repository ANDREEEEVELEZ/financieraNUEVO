<?php

namespace App\Filament\Dashboard\Pages;

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\Grupo;
use App\Models\MetricaDiariaAsesor;
use App\Models\Prestamo;
use App\Domain\Cartera\CarteraService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class AsesorPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Panel Diario';
    protected static ?string $title           = 'Panel Diario';
    protected static string  $view            = 'filament.dashboard.pages.asesor';
    protected static ?int    $navigationSort  = 1;

    public string $activeTab     = 'hoy';
    public array  $cartera       = [];
    public float  $cobradoHoy    = 0.0;
    public int    $cuotasHoyCount = 0;
    public bool   $isFiltered    = true; // false = jefe sees all asesores

    public function mount(CarteraService $carteraService): void
    {
        $user = Auth::user();
        $asesor = $this->resolveAsesor($user);
        $this->isFiltered = $asesor !== null;

        if ($asesor) {
            // Asesor: scoped to their own portfolio
            $this->cartera = $carteraService->resumen($user);
            $this->cobradoHoy = (float) (MetricaDiariaAsesor::where('asesor_id', $user->id)
                ->whereDate('fecha', today())
                ->value('cobrado') ?? 0);
        } else {
            // Jefe: aggregate across all asesores
            $this->cartera = $this->buildAgregadoCartera();
            $this->cobradoHoy = (float) MetricaDiariaAsesor::whereDate('fecha', today())->sum('cobrado');
        }

        $this->cuotasHoyCount = collect($this->cartera['cuotas_hoy'] ?? [])->count();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // Returns the Asesor model for asesor users, null for jefes/admins
    private function resolveAsesor($user): ?Asesor
    {
        if ($user->hasRole('Asesor')) {
            return Asesor::where('user_id', $user->id)->first();
        }

        return null;
    }

    private function buildAgregadoCartera(): array
    {
        $cuotasHoy = CuotaIndividual::dueToday()
            ->with(['prestamo.cliente.persona', 'prestamo.grupo'])
            ->select(['id', 'prestamo_id', 'numero_cuota', 'saldo_capital', 'fecha_vencimiento', 'estado'])
            ->get();

        return [
            'grupos_count'    => Grupo::count(),
            'clientes_count'  => Cliente::activo()->count(),
            'prestamos_count' => Prestamo::activo()->count(),
            'cartera_total'   => CuotaIndividual::whereHas('prestamo', fn ($q) => $q->activo())->sum('saldo_capital'),
            'cuotas_hoy'      => $cuotasHoy,
        ];
    }
}
