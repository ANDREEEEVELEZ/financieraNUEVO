<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContratoGrupoController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/contratos/grupo/{grupoId}', [ContratoGrupoController::class, 'imprimirContratos'])->name('contratos.grupo.imprimir');
