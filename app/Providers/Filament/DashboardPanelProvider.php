<?php

namespace App\Providers\Filament;

use App\Filament\Dashboard\Pages\AsistenteVirtual;
use App\Filament\Dashboard\Pages\Moras;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
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

class DashboardPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('dashboard')
            ->login()
            ->path('dashboard')
            ->theme(asset('css/filament/dashboard/theme.css'))
            ->colors([

              //  'primary'=> Color::Pink,
                'primary' => '#9b2c4d',
                //'secondary' => '#16b4c0',
                //'info' => '#4dc0b5',
                //'success' => '#10b981', // Verde
               // 'warning' => '#f59e0b', // Amarillo
                //'danger' => '#ef4444', // Rojo

            ])
            ->darkMode(false)
            ->font('Poppins')
            ->brandName('EMPRENDE CONMIGO SAC')


            //->brandLogo(asset('logoEmprendeConmigo.png'))
            ->discoverResources(in: app_path('Filament/Dashboard/Resources'), for: 'App\\Filament\\Dashboard\\Resources')
            ->discoverPages(in: app_path('Filament/Dashboard/Pages'), for: 'App\\Filament\\Dashboard\\Pages')
            ->pages([
                Pages\Dashboard::class,
                AsistenteVirtual::class,
                Moras::class,
                \App\Filament\Dashboard\Pages\AsesorPage::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Dashboard/Widgets'), for: 'App\\Filament\\Dashboard\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\CheckUserActive::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

}
