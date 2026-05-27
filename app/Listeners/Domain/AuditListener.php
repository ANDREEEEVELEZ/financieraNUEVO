<?php

declare(strict_types=1);

namespace App\Listeners\Domain;

use App\Contracts\AuditServiceInterface;
use App\Events\Domain\MoraCondonada;
use App\Events\Domain\PagoAprobado;
use App\Events\Domain\PagoRevertido;
use App\Events\Domain\PrestamoAprobado;
use App\Events\Domain\PrestamoDesembolsado;
use App\Events\Domain\PrestamoFirmado;
use App\Events\Domain\PrestamoRechazado;
use App\Events\Domain\ReagrupacionEjecutada;
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

    #[AsEventListener(event: PrestamoAprobado::class)]
    public function handlePrestamoAprobado(PrestamoAprobado $event): void
    {
        $this->audit->registrar(
            'prestamo.aprobado',
            $event->prestamo,
            ['estado' => 'Pendiente'],
            ['estado' => 'Aprobado'],
            ''
        );
    }

    #[AsEventListener(event: PrestamoRechazado::class)]
    public function handlePrestamoRechazado(PrestamoRechazado $event): void
    {
        $this->audit->registrar(
            'prestamo.rechazado',
            $event->prestamo,
            ['estado' => 'Pendiente'],
            ['estado' => 'Rechazado'],
            $event->motivo
        );
    }

    #[AsEventListener(event: ReagrupacionEjecutada::class)]
    public function handleReagrupacionEjecutada(ReagrupacionEjecutada $event): void
    {
        $reagrupacion = $event->reagrupacion;

        $this->audit->registrar(
            'reagrupacion.ejecutada',
            $reagrupacion,
            [
                'grupo_origen_id' => $reagrupacion->grupo_origen_id,
            ],
            [
                'grupo_nuevo_id'  => $reagrupacion->grupo_nuevo_id,
                'tipo'            => $reagrupacion->tipo,
                'monto_descuento' => $reagrupacion->monto_descuento,
            ],
            $reagrupacion->observaciones ?? ''
        );
    }
}
