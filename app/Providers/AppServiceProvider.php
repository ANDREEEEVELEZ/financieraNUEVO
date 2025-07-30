<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Models\Pago;
use App\Models\Prestamo;
use App\Models\CuotasGrupales;
use App\Models\PrestamoIndividual;
use App\Models\Retanqueo;
use App\Observers\PagoObserver;
use App\Observers\PrestamoObserver;
use App\Observers\CuotasGrupalesObserver;
use App\Observers\PrestamoIndividualObserver;
use App\Observers\RetanqueoObserver;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Registrar el servicio de notificaciones como singleton
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Forzar HTTPS en producción
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
        
        // Log para verificar que se están registrando los observers
        Log::info('AppServiceProvider: Registrando observers');
        
        // Registrar observers
        Pago::observe(PagoObserver::class);
        Prestamo::observe(PrestamoObserver::class);
        CuotasGrupales::observe(CuotasGrupalesObserver::class);
        PrestamoIndividual::observe(PrestamoIndividualObserver::class);
        Retanqueo::observe(RetanqueoObserver::class);
        
        Log::info('AppServiceProvider: Observers registrados', [
            'PagoObserver' => 'registrado',
            'PrestamoObserver' => 'registrado',
            'CuotasGrupalesObserver' => 'registrado',
            'PrestamoIndividualObserver' => 'registrado',
            'RetanqueoObserver' => 'registrado',
        ]);
    }
}