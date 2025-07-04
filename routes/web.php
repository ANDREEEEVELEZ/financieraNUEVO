<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\ContratoGrupoController;
use App\Http\Controllers\PagoPdfController;
use App\Http\Controllers\MoraPdfController;
use App\Http\Controllers\AsistenteController;

// ⚠️ Ruta temporal para limpiar cachés y rutas en producción (ELIMINAR después por seguridad)
Route::get('/fix-routes', function () {
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:cache');
    return 'Rutas y caché limpiados correctamente.';
});

// Redirección raíz al login
Route::get('/', function () {
    return redirect('/dashboard/login');
});

// Rutas para login del panel personalizado "dashboard" de Filament
Route::get('/dashboard/login', function () {
    return view('filament::auth.login'); // O asegúrate de usar tu propia vista si está personalizada
})->name('filament.dashboard.auth.login');

Route::post('/dashboard/login', '\\Filament\\Http\\Controllers\\Auth\\LoginController@store');

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

// Ruta para cerrar sesión manualmente (si no usas la de Filament directamente)
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/dashboard/login');
})->name('logout');
