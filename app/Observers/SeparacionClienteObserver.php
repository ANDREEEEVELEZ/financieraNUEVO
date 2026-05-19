<?php

namespace App\Observers;

use App\Models\SeparacionCliente;

class SeparacionClienteObserver
{
    /**
     * Run observer hooks after the surrounding DB transaction commits.
     * MorosoSeparationService dispatches SeparacionRealizada post-commit via
     * DB::afterCommit, which fires InvalidateSeparacionCache. This observer
     * intentionally has no logic — it exists only so future lifecycle hooks
     * (audit, notifications) have a home without touching the service.
     */
    public bool $afterCommit = true;
}
