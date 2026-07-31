<?php

namespace App\Domain\Prestamos\Concerns;

use App\Models\Retanqueo;
use Illuminate\Support\Facades\DB;

/**
 * Revalida que los integrantes ORIGINALES (`participacion_tipo IN
 * ['retanquea','no_retanquea']` — la misma base de quórum del Eje 1) de un
 * retanqueo sigan activos en el grupo (`grupo_cliente.fecha_salida IS NULL`)
 * en el momento de aprobar o ejecutar.
 *
 * Cierra la ventana de tiempo entre `crearSolicitudRetanqueo()` y
 * `aprobarRetanqueo()`/`ejecutarRetanqueo()`: si un integrante fue separado
 * (`MorosoSeparationService::separar()`) después de crearse la solicitud, la
 * solicitud queda con datos de participación obsoletos y NO debe
 * aprobarse/ejecutarse tal cual.
 *
 * Decisión de negocio (Eje 5, dominio-pagos-mora-retanqueo): separación
 * bloquea retanqueo, nunca al revés. `MorosoSeparationService` no conoce
 * `Retanqueo`/`RetanqueoIndividual` — el punto de enforcement vive acá, del
 * lado de retanqueo.
 */
trait ValidaParticipantesRetanqueoActivos
{
    private const MIEMBROS_ORIGINALES = ['retanquea', 'no_retanquea'];

    /**
     * @throws \Exception si algún integrante original fue separado del grupo
     *         después de crearse la solicitud de retanqueo.
     */
    private static function validarParticipantesActivos(Retanqueo $retanqueo, string $accion = 'APROBACIÓN'): void
    {
        $grupo = $retanqueo->prestamoAntiguo?->grupo;

        if (!$grupo) {
            // Retanqueo individual (sin grupo): no hay concepto de membresía
            // grupal que pueda quedar obsoleta.
            return;
        }

        $participantesOriginales = $retanqueo->retanqueosIndividuales()
            ->whereIn('participacion_tipo', self::MIEMBROS_ORIGINALES)
            ->with('cliente.persona')
            ->get();

        if ($participantesOriginales->isEmpty()) {
            return;
        }

        $clienteIds = $participantesOriginales->pluck('cliente_id')->all();

        // Evidencia AFIRMATIVA de separación: fila en el pivot grupo_cliente
        // con fecha_salida seteada (MorosoSeparationService::separar() paso 8).
        // Deliberadamente NO exigimos que exista una fila de pivot "activa"
        // para considerar al participante válido — un retanqueo real siempre
        // parte de integrantes ya adheridos al grupo, pero algunos fixtures/
        // flujos de datos no materializan esa fila de pivot explícitamente;
        // la ausencia de evidencia de separación no debe bloquear.
        $clienteIdsSeparados = DB::table('grupo_cliente')
            ->where('grupo_id', $grupo->id)
            ->whereIn('cliente_id', $clienteIds)
            ->whereNotNull('fecha_salida')
            ->pluck('cliente_id')
            ->all();

        if (empty($clienteIdsSeparados)) {
            return;
        }

        $separados = $participantesOriginales->filter(
            fn ($participante) => in_array($participante->cliente_id, $clienteIdsSeparados, true)
        );

        $nombres = $separados->map(function ($participante) {
            $persona = $participante->cliente?->persona;
            return $persona
                ? trim("{$persona->nombre} {$persona->apellidos}")
                : "cliente #{$participante->cliente_id}";
        })->implode(', ');

        throw new \Exception(
            "{$accion} BLOQUEADA: el retanqueo {$retanqueo->id} incluye integrante(s) separados del grupo después de crearse la solicitud: {$nombres}. " .
            "La solicitud debe regenerarse sin estos integrantes. Operación cancelada por seguridad financiera."
        );
    }
}
