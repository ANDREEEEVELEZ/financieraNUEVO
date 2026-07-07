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
        'saldo_pendiente',
        'estado_cuota_grupal',
        'estado_pago',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'monto_cuota_grupal' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
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
     * @deprecated Delegado a SaldoCuotaService::saldoTotal (Req 3 — single saldo service).
     */
    public function getMontoTotalAPagarAttribute()
    {
        return app(SaldoCuotaServiceInterface::class)->saldoTotal($this);
    }

    /**
     * @deprecated Delegado a SaldoCuotaService::saldoTotal (Req 3 — single saldo service).
     */
    public function getSaldoTotalPendienteAttribute()
    {
        return app(SaldoCuotaServiceInterface::class)->saldoTotal($this);
    }

    /**
     * @deprecated Delegado a SaldoCuotaService::saldoTotal (Req 3 — single saldo service).
     */
    public function saldoPendiente()
    {
        return app(SaldoCuotaServiceInterface::class)->saldoTotal($this);
    }

    /**
     * @deprecated Delegado a SaldoCuotaService::saldoMora (Req 3 — single saldo service).
     */
    public function getSaldoMoraPendiente()
    {
        return app(SaldoCuotaServiceInterface::class)->saldoMora($this);
    }

    /**
     * @deprecated Delegado a SaldoCuotaService::saldoCuota (Req 3 — single saldo service).
     */
    public function getSaldoCuotaPendiente()
    {
        return app(SaldoCuotaServiceInterface::class)->saldoCuota($this);
    }

    /**
     * @deprecated Delegado a SaldoCuotaService::moraPagada (Req 3 — single saldo service).
     */
    public function getMoraPagada()
    {
        return app(SaldoCuotaServiceInterface::class)->moraPagada($this);
    }
}
