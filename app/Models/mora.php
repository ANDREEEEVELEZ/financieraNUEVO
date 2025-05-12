<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mora extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'cuota_grupal_id',
        'dias_atraso',
        'monto_mora',
        'estado_mora',
    ];

    /**
     * Casts para convertir automáticamente campos a sus tipos adecuados.
     */
    protected $casts = [
        'monto_mora' => 'decimal:2',
        'dias_atraso' => 'integer',
    ];

    /**
     * Relación: Una mora pertenece a una cuota grupal.
     */
    public function cuotaGrupal()
    {
        return $this->belongsTo(Cuotas_Grupales::class);
    }

    /**
     * Accesor: Estado de mora con primera letra en mayúscula.
     */
    public function getEstadoMoraFormattedAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->estado_mora));
    }

    /**
     * Mutador opcional para asegurarte que el monto sea decimal con punto.
     */
    public function setMontoMoraAttribute($value)
    {
        $this->attributes['monto_mora'] = is_numeric($value)
            ? number_format($value, 2, '.', '')
            : null;
    }
}
