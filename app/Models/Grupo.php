<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;

    protected $table = 'grupos'; // Especificando el nombre de la tabla si no sigue las convenciones de Laravel
    
    protected $fillable = [
        'nombre_grupo',
        'numero_integrantes',
        'fecha_registro',
        'calificacion_grupo',
        'estado_grupo',
    ];

    protected $casts = [
        'fecha_registro' => 'date',
    ];

    public function integrantes()
    {
       //  return $this->hasMany(Integrante::class); // Suposición de una relación con una tabla de integrantes
    }
}