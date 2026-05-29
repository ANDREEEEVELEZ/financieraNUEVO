<?php

namespace App\Domain\Grupos;

use App\Contracts\SeparacionServiceInterface;
use App\Events\SeparacionRealizada;
use App\Models\Cliente;
use App\Models\CuotaIndividual;
use App\Models\CuotasGrupales;
use App\Models\Prestamo;
use App\Models\SeparacionCliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Servicio de Separación de Morosos.
 *
 * Ejecuta, dentro de una transacción atómica, todo lo necesario para
 * desligar a un integrante moroso de un préstamo grupal:
 *
 *  1. Valida precondiciones de negocio (FAIL FAST, 8 validaciones).
 *  2. Calcula deuda total del moroso con BCMath (capital + interés + mora).
 *  3. Crea un nuevo Prestamo individual con estado 'Separado' y tipo='individual'.
 *  4. Reasigna las CuotasIndividuales pendientes al nuevo préstamo.
 *  5. Reduce cada CuotaGrupal via bcsub con invariante >= 0.
 *  6. Desvincula al cliente del grupo (pivot) y decrementa numero_integrantes.
 *  7. Guarda el registro de auditoría en `separaciones_clientes`.
 *  8. Despacha evento SeparacionRealizada post-commit via DB::afterCommit.
 *
 * @throws RuntimeException si no se cumplen las precondiciones de negocio.
 */
class MorosoSeparationService implements SeparacionServiceInterface
{
    /**
     * Ejecuta la separación del cliente moroso del préstamo grupal.
     *
     * @param int    $prestamoOrigenId  ID del préstamo grupal activo.
     * @param int    $clienteId         ID del cliente a separar.
     * @param int    $ejecutadoPorId    ID del usuario que ejecuta (JC o JO).
     * @param string $motivo            Justificación de la separación.
     *
     * @return SeparacionCliente El registro de auditoría creado con relaciones cargadas.
     *
     * @throws RuntimeException
     */
    /**
     * Alternativa con objetos como parámetros (conveniencia).
     * Delega a separar() con los IDs correspondientes.
     *
     * @param Prestamo $origen         Préstamo grupal activo.
     * @param Cliente  $cliente        Cliente a separar.
     * @param User     $ejecutadoPor   Usuario que ejecuta (JC o JO).
     * @param string   $motivo         Justificación.
     * @param float    $penalizacion   Monto de penalización (0 = calculado automático desde producto).
     *
     * @return SeparacionCliente
     */
    public function separarClienteMoroso(
        Prestamo $origen,
        Cliente  $cliente,
        User     $ejecutadoPor,
        string   $motivo,
        float    $penalizacion = 0,
    ): SeparacionCliente {
        return $this->separar(
            prestamoOrigenId: $origen->id,
            clienteId:        $cliente->id,
            ejecutadoPorId:   $ejecutadoPor->id,
            motivo:           $motivo,
        );
    }

    public function separar(
        int    $prestamoOrigenId,
        int    $clienteId,
        int    $ejecutadoPorId,
        string $motivo,
    ): SeparacionCliente {
        return DB::transaction(function () use (
            $prestamoOrigenId,
            $clienteId,
            $ejecutadoPorId,
            $motivo,
        ) {
            // ── 1. Cargar el préstamo origen y bloquear para evitar race conditions ──
            $prestamoOrigen = Prestamo::with(['grupo', 'producto'])
                ->lockForUpdate()
                ->findOrFail($prestamoOrigenId);

            // ── Validaciones FAIL FAST 1–3 (sobre el préstamo) ──
            $this->validarPrestamo($prestamoOrigen);

            $grupo = $prestamoOrigen->grupo;

            // ── Validación 4: cliente pertenece al grupo ──
            $clienteEnGrupo = $grupo->clientes()
                ->where('clientes.id', $clienteId)
                ->exists();

            if (!$clienteEnGrupo) {
                throw new RuntimeException(
                    "El cliente #{$clienteId} no pertenece al grupo #{$grupo->id}."
                );
            }

            // ── Validación 5: idempotencia ──
            $yaFueSeparado = SeparacionCliente::where('cliente_id', $clienteId)
                ->where('prestamo_origen_id', $prestamoOrigenId)
                ->where('estado', 'ejecutada')
                ->exists();

            if ($yaFueSeparado) {
                throw new RuntimeException(
                    "El cliente #{$clienteId} ya fue separado del préstamo #{$prestamoOrigenId}."
                );
            }

            // ── Validación 6: mínimo de integrantes ──
            $totalIntegrantes = $grupo->clientes()->count();

            if ($totalIntegrantes <= 1) {
                throw new RuntimeException(
                    "No se puede separar al último integrante activo del grupo #{$grupo->id}."
                );
            }

            // ── Validación 7: motivo obligatorio ──
            if (trim($motivo) === '') {
                throw new RuntimeException('El motivo es obligatorio.');
            }

            // ── 2. Obtener cuotas pendientes del moroso (bloqueo pesimista) ──
            // Validación 8: cliente debe tener cuotas pendientes
            $cuotasPendientes = CuotaIndividual::where('prestamo_id', $prestamoOrigenId)
                ->where('cliente_id', $clienteId)
                ->whereIn('estado', ['pendiente', 'vencida'])
                ->lockForUpdate()
                ->get();

            if ($cuotasPendientes->isEmpty()) {
                throw new RuntimeException(
                    "El cliente #{$clienteId} no tiene cuotas pendientes en el préstamo #{$prestamoOrigenId}."
                );
            }

            // ── 3. Calcular deuda total del moroso con precisión BCMath ──
            ['capital' => $deudaCapital, 'interes' => $deudaInteres, 'mora' => $deudaMora]
                = $this->calcularDeuda($cuotasPendientes);

            $deudaTotal = bcadd(bcadd($deudaCapital, $deudaInteres, 2), $deudaMora, 2);

            // ── 4. Calcular penalización (por producto financiero) ──
            $tasaPenalizacion = (string) ($prestamoOrigen->producto?->penalizacion_separacion ?? '0.0000');
            $penalizacion = bcmul($deudaCapital, $tasaPenalizacion, 2);

            // ── 5. Crear nuevo Prestamo individual con estado 'Separado' ──
            $prestamoNuevo = $this->crearPrestamoSeparado(
                $prestamoOrigen,
                $clienteId,
                $deudaCapital,
                $deudaTotal,
                $cuotasPendientes->count(),
            );

            // ── 6. Reasignar CuotasIndividuales al nuevo préstamo ──
            CuotaIndividual::where('prestamo_id', $prestamoOrigenId)
                ->where('cliente_id', $clienteId)
                ->whereIn('estado', ['pendiente', 'vencida'])
                ->update(['prestamo_id' => $prestamoNuevo->id]);

            // ── 7. Reducir las CuotasGrupales del préstamo origen ──
            $this->reducirCuotasGrupales($prestamoOrigenId, $cuotasPendientes);

            // ── 8. Desvincular cliente del grupo (tabla pivot) ──
            $grupo->clientes()->updateExistingPivot($clienteId, [
                'fecha_salida'         => now()->toDateString(),
                'estado_grupo_cliente' => 'separado',
            ]);

            // ── 9. Decrementar número de integrantes del grupo ──
            $grupo->decrement('numero_integrantes');

            // ── 10. Registrar auditoría ──
            $separacion = SeparacionCliente::create([
                'prestamo_origen_id'  => $prestamoOrigenId,
                'prestamo_nuevo_id'   => $prestamoNuevo->id,
                'grupo_origen_id'     => $prestamoOrigen->grupo_id,
                'cliente_id'          => $clienteId,
                'ejecutado_por'       => $ejecutadoPorId,
                'deuda_capital'       => $deudaCapital,
                'deuda_interes'       => $deudaInteres,
                'deuda_mora'          => $deudaMora,
                'penalizacion_grupo'  => $penalizacion,
                'motivo'              => $motivo,
                'estado'              => 'ejecutada',
            ]);

            // ── 11. Despachar evento post-commit ──
            DB::afterCommit(function () use ($separacion) {
                event(new SeparacionRealizada($separacion));
            });

            // ── 12. Retornar con relaciones cargadas ──
            return $separacion->load(['prestamoOrigen', 'prestamoNuevo', 'cliente', 'ejecutor']);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Métodos privados de apoyo
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Valida que el préstamo origen esté en un estado y tipo que permita la separación.
     * Validaciones 1 (estado activo), 2 (tipo grupal), 3 (tiene grupo).
     */
    private function validarPrestamo(Prestamo $prestamo): void
    {
        // Validación 1: estado activo
        if (!in_array($prestamo->estado, Prestamo::ESTADOS_ACTIVOS, true)) {
            throw new RuntimeException(
                "Solo se puede separar a un integrante de un préstamo activo. " .
                "Estado actual: {$prestamo->estado}."
            );
        }

        // Validaciones 2 y 3: préstamo grupal con grupo asignado
        if ($prestamo->tipo === 'individual' || $prestamo->grupo_id === null) {
            throw new RuntimeException(
                'La separación solo aplica a préstamos grupales.'
            );
        }
    }

    /**
     * Calcula la deuda del moroso usando BCMath para precision exacta.
     *
     * @param iterable<CuotaIndividual> $cuotas
     * @return array{capital: string, interes: string, mora: string}
     */
    private function calcularDeuda(iterable $cuotas): array
    {
        $capital = '0.00';
        $interes = '0.00';
        $mora    = '0.00';

        foreach ($cuotas as $cuota) {
            $capital = bcadd($capital, (string) $cuota->saldo_capital, 2);
            $interes = bcadd($interes, (string) $cuota->saldo_interes, 2);
            $mora    = bcadd($mora, (string) number_format($cuota->moraCalculada(), 2, '.', ''), 2);
        }

        return ['capital' => $capital, 'interes' => $interes, 'mora' => $mora];
    }

    /**
     * Crea el préstamo individual de tipo 'Separado' para el moroso.
     */
    private function crearPrestamoSeparado(
        Prestamo $origen,
        int      $clienteId,
        string   $deudaCapital,
        string   $deudaTotal,
        int      $numeroCuotas,
    ): Prestamo {
        return Prestamo::create([
            'grupo_id'             => null,
            'tipo'                 => 'individual',
            'tasa_interes'         => $origen->tasa_interes,
            'monto_prestado_total' => $deudaCapital,
            'monto_devolver'       => $deudaTotal,
            'cantidad_cuotas'      => $numeroCuotas,
            'fecha_prestamo'       => now()->toDateString(),
            'fecha_desembolso'     => null,
            'frecuencia'           => $origen->frecuencia,
            'estado'               => Prestamo::ESTADO_SEPARADO,
            'calificacion'         => $origen->calificacion,
            'descripcion'          => "Separación automática del préstamo grupal #{$origen->id}.",
            'es_retanqueo'         => false,
            'prestamo_origen_id'   => $origen->id,
        ]);
    }

    /**
     * Reduce el saldo de cada CuotaGrupal del préstamo origen en la porción
     * que correspondía al moroso (capital + interés), usando BCMath.
     *
     * Invariante: saldo_pendiente resultante >= 0. Si es negativo, lanza excepción.
     *
     * @param iterable<CuotaIndividual> $cuotasDelMoroso
     */
    private function reducirCuotasGrupales(int $prestamoId, iterable $cuotasDelMoroso): void
    {
        foreach ($cuotasDelMoroso as $cuotaInd) {
            $porcionMoroso = bcadd(
                (string) $cuotaInd->saldo_capital,
                (string) $cuotaInd->saldo_interes,
                2
            );

            $cuotaGrupal = CuotasGrupales::where('prestamo_id', $prestamoId)
                ->where('numero_cuota', $cuotaInd->numero_cuota)
                ->whereIn('estado_pago', ['pendiente', 'parcial'])
                ->first();

            if (!$cuotaGrupal) {
                continue;
            }

            $nuevoSaldo = bcsub((string) $cuotaGrupal->saldo_pendiente, $porcionMoroso, 2);

            if (bccomp($nuevoSaldo, '0', 2) < 0) {
                throw new RuntimeException(
                    "Saldo grupal negativo en cuota {$cuotaInd->numero_cuota}: " .
                    "{$cuotaGrupal->saldo_pendiente} - {$porcionMoroso}"
                );
            }

            $cuotaGrupal->update(['saldo_pendiente' => $nuevoSaldo]);
        }
    }
}
