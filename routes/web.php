<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContratoGrupoController;
use App\Http\Controllers\PagoPdfController;
use App\Http\Controllers\MoraPdfController;
use App\Http\Controllers\AsistenteController;

Route::get('/', function () 
{
    return view('welcome');
});
 Route::get('/generar-esquema', [AsistenteController::class, 'guardarEsquemaEnArchivo']);

Route::get('/contratos/grupo/{grupoId}', [ContratoGrupoController::class, 'imprimirContratos'])->name('contratos.grupo.imprimir');
Route::middleware(['auth', \App\Http\Middleware\CheckUserActive::class, 'role:super_admin|Jefe de operaciones|Jefe de créditos|Asesor'])->group(function () 
{
    Route::get('/pagos/exportar/pdf', [PagoPdfController::class, 'exportar'])->name('pagos.exportar.pdf');
    Route::get('/moras/exportar-pdf', [MoraPdfController::class, 'exportar'])->name('moras.exportar.pdf');
}

);
