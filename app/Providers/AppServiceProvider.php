<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\CuotasGrupales;
use App\Models\PrestamoIndividual;
use App\Observers\PagoObserver;
use App\Observers\PrestamoObserver;
use App\Observers\CuotasGrupalesObserver;
use App\Observers\PrestamoIndividualObserver;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Log para verificar que se están registrando los observers
        Log::info('AppServiceProvider: Registrando observers');
        
        // Registrar observers
        Pago::observe(PagoObserver::class);
        Prestamo::observe(PrestamoObserver::class);
        CuotasGrupales::observe(CuotasGrupalesObserver::class);
        PrestamoIndividual::observe(PrestamoIndividualObserver::class);
        
        Log::info('AppServiceProvider: Observers registrados', [
            'PagoObserver' => 'registrado',
            'PrestamoObserver' => 'registrado',
            'CuotasGrupalesObserver' => 'registrado',
            'PrestamoIndividualObserver' => 'registrado',
        ]);
    }
}