<?php

namespace App\Models;

use App\Contracts\SaldoCuotaServiceInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuotasGrupales extends Model
{
    use HasFactory;

    protected $table = 'cuotas_grupales';

    protected $fillable = [
        'prestamo_id',
        'numero_cuota',
        'monto_cuota_grupal',
        'fecha_vencimiento',
        'estado_cuota_grupal',
        'estado_pago',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'monto_cuota_grupal' => 'decimal:2',
    ];

    public function prestamo()
    {
        return $this->belongsTo(Prestamo::class, 'prestamo_id');
    }

    // Tu método estadoLegible
    public function mora()
    {
        return $this->hasOne(Mora::class, 'cuota_grupal_id');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'cuota_grupal_id');
    }

    /**
     * Monto total a pagar de la cuota (capital + mora), delegado a
     * SaldoCuotaService::saldoTotal (Req 3 — single saldo service).
     *
     * Cambio semántico INTENCIONAL (SDD core-contable-seguridad): ahora devuelve
     * el total derivado del ledger (capital ledger-derived + mora del snapshot
     * monto_mora_calculado). YA NO lee la columna legacy `saldo_pendiente` ni
     * recalcula la mora en vivo con Mora::calcularMontoMora() gateado por
     * estado_mora, como hacía la fórmula anterior. La recomputación de mora en
     * vivo se difiere al futuro SDD de wiring de estrategia de mora
     * (mora-strategy-wiring); aquí la mora proviene del snapshot persistido.
     *
     * @deprecated Delegado a SaldoCuotaService::saldoTotal.
     *
     * @return string bcmath (escala 2), no float.
     */
    public function getMontoTotalAPagarAttribute()
    {
        return app(SaldoCuotaServiceInterface::class)->saldoTotal($this);
    }

    /**
     * @deprecated Delegado a SaldoCuotaService::saldoTotal (Req 3 — single saldo service).
     *
     * @return string bcmath (escala 2), no float.
     */
    public function getSaldoTotalPendienteAttribute()
    {
        return app(SaldoCuotaServiceInterface::class)->saldoTotal($this);
    }

    /**
     * @deprecated Delegado a SaldoCuotaService::saldoTotal (Req 3 — single saldo service).
     *
     * @return string bcmath (escala 2), no float.
     */
    public function saldoPendiente()
    {
        return app(SaldoCuotaServiceInterface::class)->saldoTotal($this);
    }

    /**
     * @deprecated Delegado a SaldoCuotaService::saldoMora (Req 3 — single saldo service).
     *
     * @return string bcmath (escala 2), no float.
     */
    public function getSaldoMoraPendiente()
    {
        return app(SaldoCuotaServiceInterface::class)->saldoMora($this);
    }

    /**
     * @deprecated Delegado a SaldoCuotaService::saldoCuota (Req 3 — single saldo service).
     *
     * @return string bcmath (escala 2), no float.
     */
    public function getSaldoCuotaPendiente()
    {
        return app(SaldoCuotaServiceInterface::class)->saldoCuota($this);
    }

    /**
     * @deprecated Delegado a SaldoCuotaService::moraPagada (Req 3 — single saldo service).
     *
     * @return string bcmath (escala 2), no float.
     */
    public function getMoraPagada()
    {
        return app(SaldoCuotaServiceInterface::class)->moraPagada($this);
    }
}
