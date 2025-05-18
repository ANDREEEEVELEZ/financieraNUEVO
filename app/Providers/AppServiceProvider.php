<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Prestamo;
use App\Observers\PrestamoObserver;

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
         Prestamo::observe(PrestamoObserver::class);
    }
}
