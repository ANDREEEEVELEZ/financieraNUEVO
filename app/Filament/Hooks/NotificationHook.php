<?php

namespace App\Filament\Hooks;

use Filament\View\PanelsRenderHook;

class NotificationHook
{
    public static function render(): string
    {
        return view('filament.hooks.notification-header')->render();
    }
}
