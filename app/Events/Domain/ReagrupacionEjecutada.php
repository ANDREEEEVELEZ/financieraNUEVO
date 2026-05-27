<?php

declare(strict_types=1);

namespace App\Events\Domain;

use App\Models\Reagrupacion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReagrupacionEjecutada
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Reagrupacion $reagrupacion)
    {
    }
}
