<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoFinanciero extends Model
{
    use HasFactory;

    protected $table = 'producto_financiero';

    protected $fillable = [
        'codigo',
        'nombre',
        'tipo',
        'tasa_interes',
        'tasa_mora',
        'permite_condonacion_mora',
        'permite_retanqueo',
        'penalizacion_separacion',
        'monto_minimo',
        'monto_maximo',
        'plazo_minimo_meses',
        'plazo_maximo_meses',
        'config_json',
        'activo',
    ];

    protected $casts = [
        'tasa_interes'           => 'decimal:4',
        'tasa_mora'              => 'decimal:4',
        'permite_condonacion_mora' => 'boolean',
        'permite_retanqueo'      => 'boolean',
        'penalizacion_separacion' => 'decimal:4',
        'monto_minimo'           => 'decimal:2',
        'monto_maximo'           => 'decimal:2',
        'config_json'            => 'array',
        'activo'                 => 'boolean',
    ];

    // Relaciones
    public function prestamos()
    {
        return $this->hasMany(Prestamo::class, 'producto_id');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeGrupales($query)
    {
        return $query->where('tipo', 'grupal');
    }

    public function scopeIndividuales($query)
    {
        return $query->where('tipo', 'individual');
    }

    // Helpers
    public function esGrupal(): bool
    {
        return $this->tipo === 'grupal';
    }

    public function esIndividual(): bool
    {
        return $this->tipo === 'individual';
    }

    /**
     * Contrato de dominio: ¿este producto financiero permite retanqueo?
     * Definido por el Súper Admin. No inferido por el sistema.
     */
    public function admiteRetanqueo(): bool
    {
        return $this->permite_retanqueo === true;
    }

    /**
     * Obtiene la configuración adicional con valor por defecto.
     */
    public function getConfig(string $key, $default = null)
    {
        return data_get($this->config_json, $key, $default);
    }
}
