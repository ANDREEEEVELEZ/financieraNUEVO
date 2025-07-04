<?php

use App\Providers\Filament\DashboardPanelProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Filament Panels
    |--------------------------------------------------------------------------
    |
    | Aquí se registran todos los paneles que usará tu aplicación Filament v3.
    | Cada panel debe tener su Provider correspondiente.
    |
    */

    'panels' => [

        'dashboard' => DashboardPanelProvider::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Panel
    |--------------------------------------------------------------------------
    |
    | Si tienes múltiples paneles y visitas la raíz del sitio (/),
    | este será el que Laravel redirigirá por defecto.
    |
    */

    'default' => 'dashboard',

];
