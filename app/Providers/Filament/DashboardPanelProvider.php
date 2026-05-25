<?php

namespace App\Providers\Filament;

use App\Filament\Dashboard\Pages\Dashboard;
use App\Filament\Dashboard\Pages\Moras;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\View\PanelsRenderHook;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Css;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Log;

class DashboardPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('dashboard')
            ->login()
            ->path('dashboard')
            ->domain(null)
            ->theme(asset('css/filament/dashboard/theme.css'))
            ->colors([
                'primary' => '#9b2c4d',
            ])
            ->darkMode(false)
            ->font('Poppins')
            ->brandName('EMPRENDE CONMIGO SAC')
            ->discoverResources(in: app_path('Filament/Dashboard/Resources'), for: 'App\\Filament\\Dashboard\\Resources')
            ->discoverPages(in: app_path('Filament/Dashboard/Pages'), for: 'App\\Filament\\Dashboard\\Pages')
            ->pages([
                Dashboard::class,
                // AsistenteVirtual::class,
                // Moras::class,
                \App\Filament\Dashboard\Pages\AsesorPage::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Dashboard/Widgets'), for: 'App\\Filament\\Dashboard\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class, // Usando el correcto de Laravel
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\SecurityHeadersMiddleware::class, // Security headers on all panel responses
                \App\Http\Middleware\DatabaseErrorHandler::class, // Manejo de errores de BD
                \App\Http\Middleware\CheckUserActive::class, // Verificación de estado activo del asesor
                'throttle:filament-login', // Rate limiting: 5 intentos/minuto por email+IP
            ])
            ->plugins([
                FilamentShieldPlugin::make()
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->authGuard('web')
            // ->passwordReset() // Deshabilitado: opción "Olvidó contraseña"
            ->emailVerification()
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.hooks.notification-header-fixed')->render()
            );
    }
}
