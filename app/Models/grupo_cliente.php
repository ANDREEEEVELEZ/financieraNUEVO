<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo_Cliente extends Model
{
    use HasFactory;

    // Tabla asociada
    protected $table = 'grupo_cliente';

    // Atributos 
    protected $fillable = [
        'grupo_id',
        'cliente_id',
        'fecha_ingreso',
        'rol',
        'estado_grupo_cliente',
    ];

    /**
     * Relación: un GrupoCliente pertenece a un grupo.
     */
    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    /**
     * Relación: un GrupoCliente pertenece a un cliente.
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}
