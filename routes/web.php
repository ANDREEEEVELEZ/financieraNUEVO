<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContratoGrupoController;
use App\Http\Controllers\PagoPdfController;
use App\Http\Controllers\PagoExportNuevoController;
use App\Http\Controllers\MoraPdfController;
use App\Http\Controllers\AsistenteController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

//  Ruta temporal para limpiar cachés y rutas en producción (eliminar después de depurar)
Route::get('/fix-routes', function () {
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:cache');
    Artisan::call('filament:assets');
    Artisan::call('config:cache');
    Artisan::call('view:cache');
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

// Ruta pública para impresión de contratos de préstamo específico
Route::get('/contratos/prestamo/{prestamoId}', [ContratoGrupoController::class, 'imprimirContratosPrestamo'])
    ->name('contratos.prestamo.imprimir');

// Ruta pública para impresión de cartilla de identificación de préstamo específico
Route::get('/cartilla/prestamo/{prestamoId}', [ContratoGrupoController::class, 'imprimirCartillaPrestamo'])
    ->name('cartilla.prestamo.imprimir');

// Ruta pública para impresión masiva de contratos por estado
Route::get('/contratos/masivos', [ContratoGrupoController::class, 'imprimirContratosMasivos'])
    ->name('contratos.masivos.imprimir');

// Rutas protegidas: requieren login y roles válidos
Route::middleware([
    'auth',
    \App\Http\Middleware\CheckUserActive::class,
    'role:super_admin|Jefe de operaciones|Jefe de creditos|Asesor'
])->group(function () {
    Route::get('/pagos/exportar/pdf', [PagoPdfController::class, 'exportar'])->name('pagos.exportar.pdf');
    Route::get('/pagos/exportar/excel', [\App\Http\Controllers\PagoExportNuevoController::class, 'export'])->name('pagos.exportar.excel');
    Route::get('/pagos/exportar', [\App\Http\Controllers\PagoExportNuevoController::class, 'export'])->name('pagos.exportar');
    Route::get('/moras/exportar-pdf', [MoraPdfController::class, 'exportar'])->name('moras.exportar.pdf');
    Route::get('/moras/exportar/excel', [MoraPdfController::class, 'exportar'])->name('moras.exportar.excel');
    Route::get('/moras/exportar', [MoraPdfController::class, 'exportar'])->name('moras.exportar');
    Route::get('/egresos/exportar', [\App\Http\Controllers\EgresoExportController::class, 'export'])->name('egresos.exportar');
    Route::get('/ingresos/exportar', [\App\Http\Controllers\IngresoExportController::class, 'export'])->name('ingresos.exportar');

    // Ruta temporal para debug: mostrar HTML del PDF en vez de descargar (usar mientras depuramos)
    Route::get('/debug/pagos-html', function (Request $request) {
        $user = $request->user();
        $query = \App\Models\Pago::query();
        $filtros = [];

        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $query->whereHas('cuotaGrupal.prestamo.grupo', function ($q) use ($asesor) {
                    $q->where('asesor_id', $asesor->id);
                });
                $filtros[] = 'Asesor: ' . ($asesor->persona ? $asesor->persona->nombres . ' ' . $asesor->persona->apellidos : 'N/A');
            } else {
                $filtros[] = 'Error: No se encontró asesor asociado';
            }
        } elseif (!$user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            $filtros[] = 'Error: No autorizado para ver todos los pagos';
        } else {
            $filtros[] = 'Acceso completo: ' . implode(', ', $user->getRoleNames()->toArray());
        }

        // Aplicar filtros simples de fecha/estado/grupo
        if ($request->filled('grupo')) {
            $grupo = \App\Models\Grupo::find($request->input('grupo'));
            if ($grupo) {
                $query->whereHas('cuotaGrupal.prestamo.grupo', function ($q) use ($request) {
                    $q->where('id', $request->input('grupo'));
                });
                $filtros[] = 'Grupo: ' . $grupo->nombre_grupo;
            }
        }

        if ($request->filled('from')) {
            $query->whereDate('fecha_pago', '>=', $request->input('from'));
            $filtros[] = 'Desde: ' . \Carbon\Carbon::parse($request->input('from'))->format('d/m/Y');
        }

        if ($request->filled('until')) {
            $query->whereDate('fecha_pago', '<=', $request->input('until'));
            $filtros[] = 'Hasta: ' . \Carbon\Carbon::parse($request->input('until'))->format('d/m/Y');
        }

        if ($request->filled('estado_pago') && $request->input('estado_pago') !== '' && $request->input('estado_pago') !== 'Todos los estados') {
            $query->where('estado_pago', $request->input('estado_pago'));
            $filtros[] = 'Estado: ' . $request->input('estado_pago');
        }

        $pagos = $query->with(['cuotaGrupal.prestamo.grupo'])->orderBy('fecha_pago', 'desc')->get();

        $service = app(\App\Services\ReporteProfesionalService::class);
        $html = $service->generarReportePagos($pagos, $filtros);

        // Si $html es instancia de DomPDF, sacar output; si es string, retornarlo
        if (is_object($html) && method_exists($html, 'output')) {
            return response($html->output(), 200)->header('Content-Type', 'text/html');
        }

        return response($html->output(), 200)->header('Content-Type', 'text/html');
    })->name('debug.pagos.html');
});

// Ruta para cerrar sesión (opcional si no usas el logout de Filament)
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/dashboard/login');
})->name('logout');

// Ruta para verificar el estado de autenticación
Route::get('/auth-check', function () {
    if (Auth::check()) {
        $user = Auth::user();
        Log::debug('User is authenticated', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email
        ]);
        return [
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'active' => $user->active ?? 'not set',
            ],
            'roles' => $user->roles ? $user->roles->pluck('name')->toArray() : []
        ];
    }
    Log::debug('User is NOT authenticated');
    return ['authenticated' => false];
});

// Ruta para forzar la autenticación manual con un usuario específico
Route::get('/force-auth/{email}', function ($email) {
    $user = \App\Models\User::where('email', $email)->first();

    if ($user) {
        Auth::login($user);
        Log::debug('Forced authentication', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);
        return redirect('/dashboard');
    }

    return 'No se encontró el usuario con email: ' . $email;
});

// Ruta para verificar el acceso a Filament
Route::get('/check-filament', function () {
    try {
        $canAccess = \Filament\Facades\Filament::auth()->check();
        $panelId = \Filament\Facades\Filament::getCurrentPanel()?->getId();
        Log::debug('Filament access check', [
            'can_access' => $canAccess,
            'panel_id' => $panelId
        ]);
        return [
            'can_access_filament' => $canAccess,
            'panel_id' => $panelId
        ];
    } catch (\Exception $e) {
        Log::error('Error checking Filament access', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return [
            'error' => true,
            'message' => $e->getMessage()
        ];
    }
});

// Ruta adicional para forzar logout (en caso de sesiones corruptas)
Route::get('/force-logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    Log::debug('Forced logout executed');
    return redirect('/dashboard/login')->with('message', 'Sesión cerrada forzosamente');
});
