<?php

use App\Providers\Filament\DashboardPanelProvider;

return [

    'panels' => [
        'dashboard' => DashboardPanelProvider::class,
    ],

    'default' => 'dashboard',

];
