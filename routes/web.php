<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContratoGrupoController;
use App\Http\Controllers\PagoPdfController;
use App\Http\Controllers\MoraPdfController;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'role:super_admin|Jefe de operaciones|Jefe de credito|Asesor'])->group(function () 
{
    Route::get('/contratos/grupo/{grupoId}', [ContratoGrupoController::class, 'imprimirContratos'])->name('contratos.grupo.imprimir');
    Route::get('/pagos/exportar/pdf', [PagoPdfController::class, 'exportar'])->name('pagos.exportar.pdf');
    Route::get('/moras/exportar-pdf', [MoraPdfController::class, 'exportar'])->name('moras.exportar.pdf');
});
