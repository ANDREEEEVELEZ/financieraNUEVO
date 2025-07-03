<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsultaAsistente extends Model
{
    use HasFactory;

    protected $table = 'consultas_asistente';

    protected $fillable = [
        'user_id',
        'consulta',
        'respuesta',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
