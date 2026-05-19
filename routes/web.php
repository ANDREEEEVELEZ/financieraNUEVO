<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContratoGrupoController;
use App\Http\Controllers\PagoPdfController;
use App\Http\Controllers\PagoExportNuevoController;
use App\Http\Controllers\MoraPdfController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

// Redireccion raiz al login del panel Filament "dashboard"
Route::get('/', function () {
    return redirect('/dashboard/login');
});

// -----------------------------------------------------------------------
// Rutas protegidas: requieren login y roles validos
// -----------------------------------------------------------------------
Route::middleware([
    'auth',
    \App\Http\Middleware\CheckUserActive::class,
    'role:super_admin|Jefe de operaciones|Jefe de creditos|Asesor'
])->group(function () {
    // Exportaciones de pagos
    Route::get('/pagos/exportar/pdf', [PagoPdfController::class, 'exportar'])->name('pagos.exportar.pdf');
    Route::get('/pagos/exportar/excel', [\App\Http\Controllers\PagoExportNuevoController::class, 'export'])->name('pagos.exportar.excel');
    Route::get('/pagos/exportar', [\App\Http\Controllers\PagoExportNuevoController::class, 'export'])->name('pagos.exportar');

    // Exportaciones de moras
    Route::get('/moras/exportar-pdf', [MoraPdfController::class, 'exportar'])->name('moras.exportar.pdf');
    Route::get('/moras/exportar/excel', [MoraPdfController::class, 'exportar'])->name('moras.exportar.excel');
    Route::get('/moras/exportar', [MoraPdfController::class, 'exportar'])->name('moras.exportar');

    // Exportaciones de egresos e ingresos
    Route::get('/egresos/exportar', [\App\Http\Controllers\EgresoExportController::class, 'export'])->name('egresos.exportar');
    Route::get('/ingresos/exportar', [\App\Http\Controllers\IngresoExportController::class, 'export'])->name('ingresos.exportar');

    // Impresion de contratos y cartillas — requiere autenticacion y rol
    Route::get('/contratos/grupo/{grupoId}', [ContratoGrupoController::class, 'imprimirContratos'])
        ->name('contratos.grupo.imprimir');
    Route::get('/contratos/prestamo/{prestamoId}', [ContratoGrupoController::class, 'imprimirContratosPrestamo'])
        ->name('contratos.prestamo.imprimir');
    Route::get('/cartilla/prestamo/{prestamoId}', [ContratoGrupoController::class, 'imprimirCartillaPrestamo'])
        ->name('cartilla.prestamo.imprimir');
    Route::get('/contratos/masivos', [ContratoGrupoController::class, 'imprimirContratosMasivos'])
        ->name('contratos.masivos.imprimir');
});

// Ruta para cerrar sesion
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/dashboard/login');
})->name('logout');
