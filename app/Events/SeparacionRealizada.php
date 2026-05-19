<?php

namespace App\Events;

use App\Models\SeparacionCliente;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SeparacionRealizada
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly SeparacionCliente $separacion)
    {
    }
}
