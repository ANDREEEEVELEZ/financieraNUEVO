<?php

namespace App\Models;

use App\Domain\Mora\Strategies\MoraCalculationStrategy;
use App\Domain\Mora\Strategies\MoraFlatPorIntegranteStrategy;
use App\Domain\Mora\Strategies\MoraPorcentualSobreSaldoStrategy;
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
        'tipo_calculo_mora',
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

    /**
     * Resuelve la estrategia de cálculo de mora configurada para este
     * producto. Las estrategias no son cuidadosamente "singleton" ni
     * resueltas por el contenedor (mismo patrón que
     * ElegibilidadRetanqueoStrategy) porque coexisten dos implementaciones
     * elegidas dinámicamente por dato, no por resolución de servicio.
     *
     * Fallback legacy: si `tipo_calculo_mora` no está seteado (fila no
     * backfillada, producto nuevo sin valor explícito), preserva el
     * comportamiento histórico según el tipo de producto — grupal → flat,
     * individual/hipotecario → porcentual.
     */
    public function moraStrategy(): MoraCalculationStrategy
    {
        $tipoCalculo = $this->tipo_calculo_mora
            ?? ($this->tipo === 'grupal' ? 'flat_por_integrante' : 'porcentual_sobre_saldo');

        return match ($tipoCalculo) {
            'porcentual_sobre_saldo' => new MoraPorcentualSobreSaldoStrategy(),
            default => new MoraFlatPorIntegranteStrategy(),
        };
    }
}
