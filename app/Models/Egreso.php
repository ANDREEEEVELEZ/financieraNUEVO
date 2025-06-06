<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Egreso extends Model
{
    use HasFactory;

    /** Tipos de egreso disponibles */
    public const TIPO_GASTO = 'gasto';
    public const TIPO_DESEMBOLSO = 'desembolso';

    protected $table = 'egresos';

    protected $fillable = [
        'tipo_egreso',
        'fecha',
        'descripcion',
        'monto',
        'prestamo_id',
        'categoria_id',
        'subcategoria_id',
        'detalle_subcategoria',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    /** Relaciones */

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class);
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function subcategoria()
    {
        return $this->belongsTo(Subcategoria::class);
    }

    /** Scopes útiles para filtrar */

    public function scopeGastos($query)
    {
        return $query->where('tipo_egreso', self::TIPO_GASTO);
    }

    public function scopeDesembolsos($query)
    {
        return $query->where('tipo_egreso', self::TIPO_DESEMBOLSO);
    }
}
