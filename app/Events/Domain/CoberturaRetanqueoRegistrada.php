<?php

namespace App\Events\Domain;

use App\Models\Pago;
use App\Models\Retanqueo;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitido cuando RegistrarCoberturaRetanqueoAction crea la cobertura ledger
 * (Pago + AplicacionPago) de una cuota del préstamo antiguo durante un
 * retanqueo. $afterCommit = true: solo se despacha si la transacción que
 * envuelve ejecutarRetanqueo() confirma (SDD core-contable-seguridad, Slice C,
 * decision D6). $retanqueo es nullable porque la Action también se puede
 * invocar de forma aislada (tests) sin un Retanqueo padre.
 */
final class CoberturaRetanqueoRegistrada
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var bool
     */
    public $afterCommit = true;

    public function __construct(
        public readonly ?Retanqueo $retanqueo,
        public readonly Pago $pago,
    ) {}
}
