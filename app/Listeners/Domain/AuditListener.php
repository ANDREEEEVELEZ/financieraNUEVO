<?php

declare(strict_types=1);

namespace App\Listeners\Domain;

use App\Contracts\AuditServiceInterface;
use App\Events\Domain\MoraCondonada;
use App\Events\Domain\PagoAprobado;
use App\Events\Domain\PagoRevertido;
use App\Events\Domain\PrestamoDesembolsado;
use App\Events\Domain\PrestamoFirmado;
use Illuminate\Events\Attributes\AsEventListener;

final class AuditListener
{
    public function __construct(private readonly AuditServiceInterface $audit) {}

    #[AsEventListener(event: PagoAprobado::class)]
    public function handlePagoAprobado(PagoAprobado $event): void
    {
        $this->audit->registrar(
            'pago.aprobado',
            $event->pago,
            [],
            ['estado_pago' => 'aprobado'],
            ''
        );
    }

    #[AsEventListener(event: PagoRevertido::class)]
    public function handlePagoRevertido(PagoRevertido $event): void
    {
        $this->audit->registrar(
            'pago.revertido',
            $event->pago,
            ['estado_pago' => 'aprobado'],
            ['estado_pago' => 'pendiente'],
            ''
        );
    }

    #[AsEventListener(event: PrestamoDesembolsado::class)]
    public function handlePrestamoDesembolsado(PrestamoDesembolsado $event): void
    {
        $this->audit->registrar(
            'prestamo.desembolsado',
            $event->prestamo,
            ['estado' => 'Firmado'],
            ['estado' => 'Activo'],
            ''
        );
    }

    #[AsEventListener(event: PrestamoFirmado::class)]
    public function handlePrestamoFirmado(PrestamoFirmado $event): void
    {
        $this->audit->registrar(
            'prestamo.firmado',
            $event->prestamo,
            ['estado' => 'Aprobado'],
            ['estado' => 'Firmado'],
            ''
        );
    }

    #[AsEventListener(event: MoraCondonada::class)]
    public function handleMoraCondonada(MoraCondonada $event): void
    {
        $this->audit->registrar(
            'mora.condonada',
            $event->objetivo,
            [],
            ['monto_condonado' => $event->montoCondonado],
            ''
        );
    }
}
