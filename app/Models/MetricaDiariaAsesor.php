<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricaDiariaAsesor extends Model
{
    use HasFactory;

    protected $table = 'metricas_diarias_asesor';

    protected $fillable = [
        'asesor_id',
        'fecha',
        'cobrado',
        'clientes_count',
        'cuotas_vigentes',
        'cuotas_pagadas',
        'cuotas_mora',
    ];

    protected $casts = [
        'fecha'   => 'date',
        'cobrado' => 'decimal:2',
    ];

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }
}
