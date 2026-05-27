<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\CuotaController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GrupoController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\PrestamoController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Public routes — no authentication required.
// Registered at both /api/auth/login AND /api/v1/auth/login.
// ---------------------------------------------------------------------------
Route::post('/auth/login', LoginController::class)
    ->middleware('throttle:api-auth')
    ->name('api.auth.login');

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', LoginController::class)
        ->middleware('throttle:api-auth')
        ->name('api.v1.auth.login');
});

// ---------------------------------------------------------------------------
// Protected routes — require a valid Sanctum bearer token and an active account.
// Canonical: /api/v1/*
// ---------------------------------------------------------------------------
Route::prefix('v1')->name('v1.')->middleware(['auth:sanctum', \App\Http\Middleware\CheckUserActive::class])->group(function () {

    Route::post('/auth/logout', LogoutController::class)->name('api.auth.logout');

    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('api.dashboard');

    // Grupos
    Route::apiResource('grupos', GrupoController::class)->only(['index', 'show']);

    // Clientes
    Route::apiResource('clientes', ClienteController::class)->only(['index', 'show']);

    // Prestamos
    Route::apiResource('prestamos', PrestamoController::class)->only(['index', 'show', 'store']);
    Route::patch('prestamos/{prestamo}/aprobar', [PrestamoController::class, 'aprobar'])->name('api.prestamos.aprobar');
    Route::patch('prestamos/{prestamo}/rechazar', [PrestamoController::class, 'rechazar'])->name('api.prestamos.rechazar');
    Route::patch('prestamos/{prestamo}/firmar', [PrestamoController::class, 'firmar'])->name('api.prestamos.firmar');
    Route::patch('prestamos/{prestamo}/desembolsar', [PrestamoController::class, 'desembolsar'])->name('api.prestamos.desembolsar');

    // Cuotas — hoy MUST be before {cuota} route model binding
    Route::get('cuotas/hoy', [CuotaController::class, 'hoy'])->name('api.cuotas.hoy');
    Route::apiResource('cuotas', CuotaController::class)->only(['index']);

    // Pagos
    Route::apiResource('pagos', PagoController::class)->only(['index', 'store']);
    Route::patch('pagos/{pago}/aprobar', [PagoController::class, 'aprobar'])->name('api.pagos.aprobar');
    Route::patch('pagos/{pago}/revertir', [PagoController::class, 'revertir'])->name('api.pagos.revertir');
});

// ---------------------------------------------------------------------------
// Legacy aliases — one release cycle only.
// Duplicate registrations (NOT Route::redirect) to preserve PATCH/POST bodies.
// ---------------------------------------------------------------------------
Route::middleware(['auth:sanctum', \App\Http\Middleware\CheckUserActive::class])->group(function () {

    Route::post('/auth/logout', LogoutController::class)->name('api.auth.logout.legacy');

    // Dashboard
    Route::get('/dashboard', DashboardController::class)->name('api.dashboard.legacy');

    // Grupos
    Route::apiResource('grupos', GrupoController::class)->only(['index', 'show'])->names([
        'index' => 'api.grupos.index.legacy',
        'show'  => 'api.grupos.show.legacy',
    ]);

    // Clientes
    Route::apiResource('clientes', ClienteController::class)->only(['index', 'show'])->names([
        'index' => 'api.clientes.index.legacy',
        'show'  => 'api.clientes.show.legacy',
    ]);

    // Prestamos
    Route::apiResource('prestamos', PrestamoController::class)->only(['index', 'show', 'store'])->names([
        'index' => 'api.prestamos.index.legacy',
        'show'  => 'api.prestamos.show.legacy',
        'store' => 'api.prestamos.store.legacy',
    ]);
    Route::patch('prestamos/{prestamo}/aprobar', [PrestamoController::class, 'aprobar'])->name('api.prestamos.aprobar.legacy');
    Route::patch('prestamos/{prestamo}/rechazar', [PrestamoController::class, 'rechazar'])->name('api.prestamos.rechazar.legacy');
    Route::patch('prestamos/{prestamo}/firmar', [PrestamoController::class, 'firmar'])->name('api.prestamos.firmar.legacy');
    Route::patch('prestamos/{prestamo}/desembolsar', [PrestamoController::class, 'desembolsar'])->name('api.prestamos.desembolsar.legacy');

    // Cuotas
    Route::get('cuotas/hoy', [CuotaController::class, 'hoy'])->name('api.cuotas.hoy.legacy');
    Route::apiResource('cuotas', CuotaController::class)->only(['index'])->names([
        'index' => 'api.cuotas.index.legacy',
    ]);

    // Pagos
    Route::apiResource('pagos', PagoController::class)->only(['index', 'store'])->names([
        'index' => 'api.pagos.index.legacy',
        'store' => 'api.pagos.store.legacy',
    ]);
    Route::patch('pagos/{pago}/aprobar', [PagoController::class, 'aprobar'])->name('api.pagos.aprobar.legacy');
    Route::patch('pagos/{pago}/revertir', [PagoController::class, 'revertir'])->name('api.pagos.revertir.legacy');
});
