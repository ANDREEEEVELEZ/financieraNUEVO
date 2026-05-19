<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\CuotasGrupales;
use App\Models\Grupo;
use App\Models\Mora;
use App\Models\Pago;
use App\Models\Persona;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Models\Retanqueo;
use App\Models\SeparacionCliente;
use App\Observers\AsesorObserver;
use App\Observers\ClienteObserver;
use App\Observers\CuotasGrupalesObserver;
use App\Observers\GrupoObserver;
use App\Observers\MoraObserver;
use App\Observers\PagoObserver;
use App\Observers\PersonaObserver;
use App\Observers\PrestamoIndividualObserver;
use App\Observers\PrestamoObserver;
use App\Observers\RetanqueoObserver;
use App\Observers\SeparacionClienteObserver;
use App\Services\NotificationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService();
        });
    }

    public function boot(): void
    {
        // Force HTTPS on every non-local environment (covers staging and production)
        if (!$this->app->environment('local', 'testing')) {
            URL::forceScheme('https');
        }

        // -----------------------------------------------------------------------
        // Rate limiters applied via throttle middleware on specific routes.
        // The filament-login limiter skips authenticated requests so that normal
        // dashboard navigation does not consume the login-attempt bucket.
        // -----------------------------------------------------------------------
        RateLimiter::for('filament-login', function (Request $request) {
            if ($request->user()) {
                return Limit::none();
            }
            return Limit::perMinute(5)->by(($request->input('email') ?? '') . '|' . $request->ip());
        });

        RateLimiter::for('api-auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->input('email') ?? $request->ip());
        });

        // All observers are registered here — single canonical location.
        // AsesorObserver and PersonaObserver were previously in their model boot()
        // methods; consolidated here for discoverability.
        Asesor::observe(AsesorObserver::class);
        Cliente::observe(ClienteObserver::class);
        CuotasGrupales::observe(CuotasGrupalesObserver::class);
        Grupo::observe(GrupoObserver::class);
        Mora::observe(MoraObserver::class);
        Pago::observe(PagoObserver::class);
        Persona::observe(PersonaObserver::class);
        Prestamo::observe(PrestamoObserver::class);
        PrestamoIndividual::observe(PrestamoIndividualObserver::class);
        Retanqueo::observe(RetanqueoObserver::class);
        SeparacionCliente::observe(SeparacionClienteObserver::class);
    }
}
