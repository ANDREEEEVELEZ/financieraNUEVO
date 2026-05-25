<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteScoring extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'score',
        'grado',
        'vigente',
    ];

    protected $casts = [
        'score'   => 'integer',
        'vigente' => 'boolean',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
