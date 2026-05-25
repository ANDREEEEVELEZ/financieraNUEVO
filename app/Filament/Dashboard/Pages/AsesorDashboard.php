<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\ClienteScoring;
use App\Models\CuotaIndividual;
use App\Models\Grupo;
use App\Models\MetaMensual;
use App\Models\MetricaDiariaAsesor;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Services\MetaCobranzaService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AsesorDashboard extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Dashboard Cartera';
    protected static ?string $title           = 'Dashboard Cartera';
    protected static string  $view            = 'filament.dashboard.pages.asesor-dashboard';
    protected static ?string $slug            = 'asesor-dashboard';
    protected static ?int    $navigationSort  = 2;

    public string  $activeTab           = 'cobranza';
    public string  $period              = 'mes';
    public array   $meta                = [];
    public array   $gradoData           = [];
    public bool    $isOperacionesLocked = true;
    public bool    $isFiltered          = true; // false = jefe sees all asesores

    public function mount(MetaCobranzaService $metaService): void
    {
        $user   = Auth::user();
        $asesor = $this->resolveAsesor($user);
        $this->isFiltered          = $asesor !== null;
        $this->isOperacionesLocked = ! $user->hasAnyRole(['Jefe de operaciones', 'super_admin']);

        if ($asesor) {
            $this->meta = $metaService->calcular($user, now()->startOfMonth());

            $clienteIds      = $asesor->clientes()->pluck('id');
            $this->gradoData = ClienteScoring::whereIn('cliente_id', $clienteIds)
                ->where('vigente', true)
                ->selectRaw('grado, COUNT(*) as cnt')
                ->groupBy('grado')
                ->pluck('cnt', 'grado')
                ->toArray();
        } else {
            // Jefe: aggregate meta across ALL asesores for current month
            $mes = now();
            $cobrado = MetricaDiariaAsesor::whereYear('fecha', $mes->year)
                ->whereMonth('fecha', $mes->month)
                ->sum('cobrado');
            $metaMonto = MetaMensual::where('anio', $mes->year)
                ->where('mes', $mes->month)
                ->sum('meta_monto') ?: 1;
            $this->meta = [
                'cobrado' => (float) $cobrado,
                'meta'    => (float) $metaMonto,
                'pct'     => round(($cobrado / $metaMonto) * 100, 1),
            ];

            $this->gradoData = ClienteScoring::where('vigente', true)
                ->selectRaw('grado, COUNT(*) as cnt')
                ->groupBy('grado')
                ->pluck('cnt', 'grado')
                ->toArray();
        }
    }

    // Returns the Asesor model for asesor users, null for jefes/admins
    private function resolveAsesor($user): ?Asesor
    {
        if ($user->hasRole('Asesor')) {
            return Asesor::where('user_id', $user->id)->first();
        }

        return null;
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
        $asesor = $this->resolveAsesor($user);

        $cobrado = $this->meta['cobrado'] ?? 0;
        $meta    = $this->meta['meta']    ?? 1;

        if ($asesor) {
            $cuotasVigentes = CuotaIndividual::ofAsesor($asesor)->whereIn('estado', ['pendiente', 'pagada'])->count();
            $cuotasPagadas  = CuotaIndividual::ofAsesor($asesor)->where('estado', 'pagada')->count();
            $carteraTotal   = CuotaIndividual::whereHas('prestamo', fn ($q) => $q->ofAsesor($asesor)->activo())->sum('saldo_capital');
            $moraPar30      = CuotaIndividual::ofAsesor($asesor)->where('estado', 'vencida')->where('fecha_vencimiento', '<', now()->subDays(30))->sum('saldo_capital');
            $topMorosos     = CuotaIndividual::ofAsesor($asesor)->where('estado', 'vencida')->with(['prestamo.cliente.persona', 'prestamo.grupo'])->selectRaw('*, DATEDIFF(CURDATE(), fecha_vencimiento) as dias_mora')->orderByDesc('dias_mora')->limit(5)->get();
        } else {
            // Jefe: all asesores, no scope filter
            $cuotasVigentes = CuotaIndividual::whereIn('estado', ['pendiente', 'pagada'])->count();
            $cuotasPagadas  = CuotaIndividual::where('estado', 'pagada')->count();
            $carteraTotal   = CuotaIndividual::whereHas('prestamo', fn ($q) => $q->activo())->sum('saldo_capital');
            $moraPar30      = CuotaIndividual::where('estado', 'vencida')->where('fecha_vencimiento', '<', now()->subDays(30))->sum('saldo_capital');
            $topMorosos     = CuotaIndividual::where('estado', 'vencida')->with(['prestamo.cliente.persona', 'prestamo.grupo'])->selectRaw('*, DATEDIFF(CURDATE(), fecha_vencimiento) as dias_mora')->orderByDesc('dias_mora')->limit(5)->get();
        }

        $eficiencia = $cuotasVigentes > 0 ? round(($cuotasPagadas / $cuotasVigentes) * 100, 1) : 0;
        $par30      = $carteraTotal > 0   ? round(($moraPar30 / $carteraTotal) * 100, 1) : 0;
        $moraBuckets = $this->getMoraBuckets($asesor);

        return compact('cobrado', 'meta', 'eficiencia', 'par30', 'moraBuckets', 'topMorosos');
    }

    public function getCarteraData(): array
    {
        $user   = Auth::user();
        $asesor = $this->resolveAsesor($user);

        if ($asesor) {
            $grupos     = Grupo::where('asesor_id', $asesor->id)->with('clientes')->get();
            $ciclosProm = Cliente::ofAsesor($asesor)->activo()->avg('ciclo') ?? 0;
        } else {
            $grupos     = Grupo::with('clientes')->get();
            $ciclosProm = Cliente::activo()->avg('ciclo') ?? 0;
        }

        return [
            'grupos'      => $grupos,
            'ciclos_prom' => round((float) $ciclosProm, 1),
        ];
    }

    public function getPagosData(): array
    {
        $user   = Auth::user();
        $asesor = $this->resolveAsesor($user);

        $cobradoHoy = $asesor
            ? (float) (MetricaDiariaAsesor::where('asesor_id', $user->id)->whereDate('fecha', today())->value('cobrado') ?? 0)
            : (float) MetricaDiariaAsesor::whereDate('fecha', today())->sum('cobrado');

        $pagosQuery = Pago::whereDate('fecha_pago', today());
        if ($asesor) {
            $pagosQuery->whereHas('cuotaGrupal.prestamo.grupo', fn ($q) => $q->where('asesor_id', $asesor->id));
        }

        $pagosHoy = $pagosQuery
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

    private function getMoraBuckets(?Asesor $asesor): array
    {
        $buckets = [
            ['range' => '0–7d',   'min' => 0,  'max' => 7,   'label' => 'leve',     'color' => 'green'],
            ['range' => '7–15d',  'min' => 7,  'max' => 15,  'label' => 'observ.',  'color' => 'yellow'],
            ['range' => '15–30d', 'min' => 15, 'max' => 30,  'label' => 'atención', 'color' => 'orange'],
            ['range' => '30–60d', 'min' => 30, 'max' => 60,  'label' => 'crítico',  'color' => 'red'],
            ['range' => '60d+',   'min' => 60, 'max' => null, 'label' => 'castigo', 'color' => 'dark-red'],
        ];

        foreach ($buckets as &$bucket) {
            $query = $asesor
                ? CuotaIndividual::ofAsesor($asesor)->where('estado', 'vencida')
                : CuotaIndividual::where('estado', 'vencida');

            $query->whereRaw('DATEDIFF(CURDATE(), fecha_vencimiento) >= ?', [$bucket['min']]);
            if ($bucket['max'] !== null) {
                $query->whereRaw('DATEDIFF(CURDATE(), fecha_vencimiento) < ?', [$bucket['max']]);
            }

            $bucket['count'] = $query->count();
        }

        return $buckets;
    }
}

