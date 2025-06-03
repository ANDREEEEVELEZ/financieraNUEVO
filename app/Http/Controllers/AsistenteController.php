<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AsistenteController extends Controller
{
    // Esta función genera el esquema de la BD y lo guarda en un archivo txt
    public function guardarEsquemaEnArchivo()
    {
        // Obtener todas las tablas
        $tablas = DB::select('SHOW TABLES');

        $esquema = "";

        foreach ($tablas as $tablaObj) {
            // Obtener el nombre de la tabla (cada objeto tiene el nombre de la tabla en el primer campo)
            $tabla = array_values((array) $tablaObj)[0];

            // Obtener las columnas de la tabla
            $columnas = DB::select("SHOW COLUMNS FROM {$tabla}");

            // Obtener solo los nombres de las columnas y unirlas con coma
            $nombresColumnas = collect($columnas)->pluck('Field')->join(', ');

            // Crear línea con nombre de tabla y sus columnas
            $esquema .= "- {$tabla} ({$nombresColumnas})\n";
        }

        // Guardar el esquema en un archivo .txt dentro de storage/app/
        Storage::put('esquema_bd.txt', $esquema);

        // Opcional: mostrar el esquema en pantalla para verificar
        return response($esquema, 200)->header('Content-Type', 'text/plain');
    }
}
