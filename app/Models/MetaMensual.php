<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaMensual extends Model
{
    use HasFactory;

    protected $fillable = [
        'asesor_id',
        'anio',
        'mes',
        'meta_monto',
    ];

    protected $casts = [
        'anio'       => 'integer',
        'mes'        => 'integer',
        'meta_monto' => 'decimal:2',
    ];

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }
}
