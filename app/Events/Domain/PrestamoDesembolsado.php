<?php

namespace App\Events\Domain;

use App\Models\Prestamo;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PrestamoDesembolsado
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Prestamo $prestamo)
    {
    }
}
