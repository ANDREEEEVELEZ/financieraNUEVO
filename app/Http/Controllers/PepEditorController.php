<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;

class PepEditorController extends Controller
{
    public function show(Cliente $cliente)
    {
        // Verificar que el cliente sea PEP
        if ($cliente->condicion_personal !== 'PEP') {
            return redirect()->route('filament.dashboard.resources.clientes.index')
                ->with('error', 'Este cliente no tiene condición PEP.');
        }
        
        // Verificar que el cliente esté activo
        if ($cliente->estado_cliente !== 'Activo') {
            return redirect()->route('filament.dashboard.resources.clientes.index')
                ->with('error', 'Este cliente no está activo.');
        }
        
        // Cargar la relación persona
        $cliente->load('persona');
        
        return view('pep-editor', [
            'cliente' => $cliente
        ]);
    }
}
