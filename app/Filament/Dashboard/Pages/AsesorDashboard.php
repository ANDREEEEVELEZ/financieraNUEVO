<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Models\Asesor;
use App\Models\ClienteScoring;
use App\Models\CuotaIndividual;
use App\Models\MetricaDiariaAsesor;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Services\MetaCobranzaService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class AsesorDashboard extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?string $title           = 'Dashboard Asesor';
    protected static string  $view            = 'filament.dashboard.pages.asesor-dashboard';
    protected static ?string $slug            = 'asesor-dashboard';
    protected static ?int    $navigationSort  = 2;

    public string $activeTab          = 'cobranza';
    public string $period             = 'mes';
    public array  $meta               = [];
    public array  $gradoData          = [];
    public bool   $isOperacionesLocked = true;

    public function mount(MetaCobranzaService $metaService): void
    {
        $user            = Auth::user();
        $this->meta      = $metaService->calcular($user, now()->startOfMonth());
        $this->isOperacionesLocked = ! $user->hasAnyRole(['Jefe de operaciones', 'super_admin']);

        $asesor = Asesor::where('user_id', $user->id)->first();
        if ($asesor) {
            $clienteIds      = $asesor->clientes()->pluck('id');
            $this->gradoData = ClienteScoring::whereIn('cliente_id', $clienteIds)
                ->where('vigente', true)
                ->selectRaw('grado, COUNT(*) as cnt')
                ->groupBy('grado')
                ->pluck('cnt', 'grado')
                ->toArray();
        }
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function setPeriod(string $period): void
    {
        if (in_array($period, ['hoy', 'semana', 'mes'], true)) {
            $this->period = $period;
        }
        // 'trim' and 'anio' are display-only (Próximamente) — silently ignored
    }

    public function getCobranzaData(): array
    {
        $user   = Auth::user();
        $asesor = Asesor::where('user_id', $user->id)->first();
        if (! $asesor) {
            return [];
        }

        $cobrado = $this->meta['cobrado'] ?? 0;
        $meta    = $this->meta['meta']    ?? 1;

        // Eficiencia: cuotas pagadas / cuotas vigentes (pendiente + pagada)
        $cuotasVigentes = CuotaIndividual::ofAsesor($asesor)
            ->whereIn('estado', ['pendiente', 'pagada'])
            ->count();
        $cuotasPagadas = CuotaIndividual::ofAsesor($asesor)
            ->where('estado', 'pagada')
            ->count();
        $eficiencia = $cuotasVigentes > 0
            ? round(($cuotasPagadas / $cuotasVigentes) * 100, 1)
            : 0;

        // PAR 30: saldo_capital de cuotas vencidas >30 days / cartera total
        $carteraTotal = CuotaIndividual::whereHas(
            'prestamo',
            fn ($q) => $q->ofAsesor($asesor)->activo()
        )->sum('saldo_capital');

        $moraPar30 = CuotaIndividual::ofAsesor($asesor)
            ->where('estado', 'vencida')
            ->where('fecha_vencimiento', '<', now()->subDays(30))
            ->sum('saldo_capital');

        $par30 = $carteraTotal > 0
            ? round(($moraPar30 / $carteraTotal) * 100, 1)
            : 0;

        $moraBuckets = $this->getMoraBuckets($asesor);

        $topMorosos = CuotaIndividual::ofAsesor($asesor)
            ->where('estado', 'vencida')
            ->with(['prestamo.cliente.persona', 'prestamo.grupo'])
            ->selectRaw('*, DATEDIFF(CURDATE(), fecha_vencimiento) as dias_mora')
            ->orderByDesc('dias_mora')
            ->limit(5)
            ->get();

        return compact('cobrado', 'meta', 'eficiencia', 'par30', 'moraBuckets', 'topMorosos');
    }

    public function getCarteraData(): array
    {
        $user   = Auth::user();
        $asesor = Asesor::where('user_id', $user->id)->first();
        if (! $asesor) {
            return [];
        }

        $grupos     = \App\Models\Grupo::where('asesor_id', $asesor->id)->with('clientes')->get();
        $ciclosProm = \App\Models\Cliente::ofAsesor($asesor)->activo()->avg('ciclo') ?? 0;

        return [
            'grupos'      => $grupos,
            'ciclos_prom' => round((float) $ciclosProm, 1),
        ];
    }

    public function getPagosData(): array
    {
        $user   = Auth::user();
        $asesor = Asesor::where('user_id', $user->id)->first();
        if (! $asesor) {
            return [];
        }

        $cobradoHoy = MetricaDiariaAsesor::where('asesor_id', $user->id)
            ->whereDate('fecha', today())
            ->value('cobrado') ?? 0;

        $pagosHoy = Pago::whereDate('fecha_pago', today())
            ->whereHas(
                'cuotaGrupal.prestamo.grupo',
                fn ($q) => $q->where('asesor_id', $asesor->id)
            )
            ->selectRaw('estado_pago, COUNT(*) as cnt')
            ->groupBy('estado_pago')
            ->pluck('cnt', 'estado_pago')
            ->toArray();

        return [
            'cobrado_hoy' => $cobradoHoy,
            'aprobados'   => $pagosHoy['aprobado']  ?? 0,
            'pendientes'  => $pagosHoy['pendiente'] ?? 0,
            'rechazados'  => $pagosHoy['rechazado'] ?? 0,
        ];
    }

    private function getMoraBuckets(Asesor $asesor): array
    {
        $buckets = [
            ['range' => '0–7d',   'min' => 0,  'max' => 7,  'label' => 'leve',      'color' => 'green'],
            ['range' => '7–15d',  'min' => 7,  'max' => 15, 'label' => 'observ.',   'color' => 'yellow'],
            ['range' => '15–30d', 'min' => 15, 'max' => 30, 'label' => 'atención',  'color' => 'orange'],
            ['range' => '30–60d', 'min' => 30, 'max' => 60, 'label' => 'crítico',   'color' => 'red'],
            ['range' => '60d+',   'min' => 60, 'max' => null, 'label' => 'castigo', 'color' => 'dark-red'],
        ];

        foreach ($buckets as &$bucket) {
            $query = CuotaIndividual::ofAsesor($asesor)->where('estado', 'vencida');
            $min   = $bucket['min'];
            $max   = $bucket['max'];

            $query->whereRaw('DATEDIFF(CURDATE(), fecha_vencimiento) >= ?', [$min]);
            if ($max !== null) {
                $query->whereRaw('DATEDIFF(CURDATE(), fecha_vencimiento) < ?', [$max]);
            }

            $bucket['count'] = $query->count();
        }

        return $buckets;
    }
}
