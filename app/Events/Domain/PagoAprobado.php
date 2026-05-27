<?php

namespace App\Events\Domain;

use App\Models\Pago;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PagoAprobado
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Pago $pago)
    {
    }
}
