<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContratoGrupoController;
use App\Http\Controllers\PagoPdfController;
use App\Http\Controllers\MoraPdfController;
use App\Http\Controllers\AsistenteController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

// ⚠️ Ruta temporal para limpiar cachés y rutas en producción (eliminar después de depurar)
Route::get('/fix-routes', function () {
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:cache');
    return 'Rutas y caché limpiados correctamente.';
});

// Redirección raíz al login del panel Filament "dashboard"
Route::get('/', function () {
    return redirect('/dashboard/login');
});

// Ruta pública para generar esquema desde el Asistente Virtual
Route::get('/generar-esquema', [AsistenteController::class, 'guardarEsquemaEnArchivo']);

// Ruta pública para impresión de contratos de grupo
Route::get('/contratos/grupo/{grupoId}', [ContratoGrupoController::class, 'imprimirContratos'])
    ->name('contratos.grupo.imprimir');

// Rutas protegidas: requieren login y roles válidos
Route::middleware([
    'auth',
    \App\Http\Middleware\CheckUserActive::class,
    'role:super_admin|Jefe de operaciones|Jefe de creditos|Asesor'
])->group(function () {
    Route::get('/pagos/exportar/pdf', [PagoPdfController::class, 'exportar'])->name('pagos.exportar.pdf');
    Route::get('/moras/exportar-pdf', [MoraPdfController::class, 'exportar'])->name('moras.exportar.pdf');
});

// Ruta para cerrar sesión (opcional si no usas el logout de Filament)
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/dashboard/login');
})->name('logout');

// ✅ Ruta POST necesaria para el login de Filament (panel "dashboard")
Route::post('/dashboard/login', '\\Filament\\Http\\Controllers\\Auth\\LoginController@store');
