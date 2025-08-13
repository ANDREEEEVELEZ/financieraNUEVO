<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeclaracionJurada extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'nombres',
        'apellidos', 
        'dni',
        'nacionalidad',
        'estado_civil',
        'conyuge_nombres',
        'domicilio',
        'distrito',
        'provincia',
        'departamento',
        'ocupacion',
        'telefono_fijo',
        'celular',
        'correo',
        'tipo_documento',
        'numero_documento',
        'otro_documento_detalle',
        'tipo_via',
        'nombre_via',
        'urbanizacion',
        'complejo_zona_sector',
        'interior',
        'departamento_numero',
        'proposito_relacion',
        'es_pep',
        'cargo_publico',
        'entidad_publica',
        'fecha_inicio_cargo',
        'fecha_fin_cargo',
        'parentesco_pep',
        'actua_por',
        'representado_nombres',
        'representado_apellidos',
        'representado_documento',
        'fecha_declaracion',
        'lugar_declaracion',
        'datos_adicionales'
    ];

    protected $casts = [
        'fecha_inicio_cargo' => 'date',
        'fecha_fin_cargo' => 'date',
        'fecha_declaracion' => 'datetime',
        'datos_adicionales' => 'array'
    ];

    /**
     * Relación con Cliente
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Crear declaración jurada desde datos del cliente
     */
    public static function crearDesdeCliente(Cliente $cliente, array $datosAdicionales = [])
    {
        return static::create([
            'cliente_id' => $cliente->id,
            'nombres' => strtoupper($cliente->persona->nombre),
            'apellidos' => strtoupper($cliente->persona->apellidos),
            'dni' => $cliente->persona->DNI,
            'nacionalidad' => 'PERUANA',
            'estado_civil' => $cliente->persona->estado_civil,
            'domicilio' => strtoupper($cliente->persona->direccion),
            'distrito' => strtoupper($cliente->persona->distrito),
            'provincia' => 'SULLANA',
            'departamento' => 'PIURA',
            'ocupacion' => strtoupper($cliente->actividad),
            'celular' => $cliente->persona->celular,
            'correo' => $cliente->persona->correo,
            'tipo_documento' => 'DNI',
            'numero_documento' => $cliente->persona->DNI,
            'tipo_via' => 'Jr.',
            'nombre_va' => strtoupper($cliente->persona->direccion),
            'proposito_relacion' => 'Prestamo',
            'es_pep' => 'NO_SOY',
            'actua_por' => 'MI_MISMO',
            'lugar_declaracion' => 'Sullana',
            ...$datosAdicionales
        ]);
    }
}
