<?php

declare(strict_types=1);

namespace App\Events\Domain;

use App\Models\Prestamo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PrestamoAprobado
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Prestamo $prestamo)
    {
    }
}
