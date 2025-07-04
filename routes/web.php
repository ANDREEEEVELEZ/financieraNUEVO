<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContratoGrupoController;
use App\Http\Controllers\PagoPdfController;
use App\Http\Controllers\MoraPdfController;
use App\Http\Controllers\AsistenteController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
Route::post('/dashboard/login', [App\Http\Controllers\CustomLoginController::class, 'store']);

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
            'roles' => $user->getRoleNames()->toArray()
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