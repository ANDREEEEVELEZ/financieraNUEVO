<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subcategoria extends Model
{
    use HasFactory;

    protected $table = 'subcategorias';

    protected $fillable = [
        'categoria_id',
        'nombre_subcategoria',
    ];

    /** Relaciones */

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    /** Accessors y Mutators */

    public function getNombreSubcategoriaAttribute($value)
    {
        return ucfirst($value);
    }

    public function setNombreSubcategoriaAttribute($value)
    {
        $this->attributes['nombre_subcategoria'] = strtolower($value);
    }
}
