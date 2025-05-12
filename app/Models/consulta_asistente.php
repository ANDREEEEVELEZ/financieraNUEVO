<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class consulta_asistente extends Model
{
    use HasFactory;

    protected $table = 'consultas_asistente';

    protected $fillable = [
        'user_id',
        'consulta',
        'respuesta',
    ];

    /**
     * Relación con el modelo User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}