<?php

namespace App\Services;

use App\Models\Prestamo;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Asesor;
use App\Models\Mora;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Obtiene todas las notificaciones para el usuario actual
     */
    public function getNotifications()
    {
        $user = Auth::user();
        $notifications = [];

        try {
            if (!$user) {
                Log::warning('NotificationService: Usuario no autenticado');
                return $notifications;
            }

            Log::info('NotificationService: Cargando notificaciones para usuario', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_roles' => $user->roles ? $user->roles->pluck('name')->toArray() : []
            ]);

            if ($user && $user->hasRole('Asesor')) {
                $asesorNotifications = $this->getAsesorNotifications($user);
                $notifications = array_merge($notifications, $asesorNotifications);
                Log::info('NotificationService: Notificaciones de asesor cargadas', ['count' => count($asesorNotifications)]);
            }

            if ($user && $user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
                $supervisorNotifications = $this->getSupervisorNotifications($user);
                $notifications = array_merge($notifications, $supervisorNotifications);
                Log::info('NotificationService: Notificaciones de supervisor cargadas', ['count' => count($supervisorNotifications)]);
            }

            // Ordenar por fecha más reciente
            usort($notifications, function ($a, $b) {
                return $b['created_at']->timestamp - $a['created_at']->timestamp;
            });

            Log::info('NotificationService: Total de notificaciones', ['total' => count($notifications)]);
            
        } catch (\Exception $e) {
            Log::error('NotificationService: Error al obtener notificaciones', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return $notifications;
    }

    /**
     * Obtiene notificaciones para asesores
     */
    private function getAsesorNotifications($user)
    {
        $notifications = [];
        $asesor = Asesor::where('user_id', $user->id)->first();

        if (!$asesor) {
            return $notifications;
        }

        // Préstamos aprobados o rechazados del asesor
        $prestamosRespondidos = Prestamo::whereHas('grupo', function ($query) use ($asesor) {
            $query->where('asesor_id', $asesor->id);
        })
        ->whereIn('estado', ['Aprobado', 'Rechazado'])
        ->where('updated_at', '>=', Carbon::now()->subDays(7)) // Últimos 7 días
        ->get();

        foreach ($prestamosRespondidos as $prestamo) {
            $notifications[] = [
                'id' => 'prestamo_respuesta_' . $prestamo->id,
                'type' => 'prestamo_respuesta',
                'title' => 'Respuesta a tu solicitud de préstamo',
                'description' => "Préstamo #{$prestamo->id} fue {$prestamo->estado}",
                'url' => "/dashboard/prestamos/{$prestamo->id}",
                'icon' => $prestamo->estado === 'Aprobado' ? '✅' : '❌',
                'color' => $prestamo->estado === 'Aprobado' ? 'success' : 'danger',
                'created_at' => $prestamo->updated_at,
            ];
        }

        return $notifications;
    }

    /**
     * Obtiene notificaciones para supervisores
     */
    private function getSupervisorNotifications($user)
    {
        $notifications = [];

        // Debug: Log cuántos préstamos hay en cada estado
        $todosLosPrestamos = Prestamo::select('estado')->get();
        $prestamosPorEstado = $todosLosPrestamos->groupBy('estado')->map->count()->toArray();
        Log::info('Préstamos por estado:', $prestamosPorEstado);

        // Préstamos pendientes de aprobación - buscar varios posibles estados
        $estadosPendientes = ['Pendiente', 'pendiente', 'PENDIENTE', 'En revisión', 'en revision'];
        $prestamosPendientes = Prestamo::whereIn('estado', $estadosPendientes)->get();
        Log::info('Préstamos pendientes encontrados:', [
            'count' => $prestamosPendientes->count(),
            'estados_buscados' => $estadosPendientes
        ]);
        
        foreach ($prestamosPendientes as $prestamo) {
            $notifications[] = [
                'id' => 'prestamo_pendiente_' . $prestamo->id,
                'type' => 'prestamo_pendiente',
                'title' => 'Préstamo pendiente de aprobación',
                'description' => "Préstamo #{$prestamo->id} por $" . number_format((float)$prestamo->monto_prestado_total, 2),
                'url' => "/dashboard/prestamos/{$prestamo->id}/edit",
                'icon' => '⏳',
                'color' => 'warning',
                'created_at' => $prestamo->created_at,
            ];
        }

        // Pagos pendientes de validación
        $pagosPendientes = Pago::where('estado_pago', 'pendiente')->get();
        
        foreach ($pagosPendientes as $pago) {
            $notifications[] = [
                'id' => 'pago_pendiente_' . $pago->id,
                'type' => 'pago_pendiente',
                'title' => 'Pago pendiente de validación',
                'description' => "Pago de $" . number_format((float)$pago->monto_pago, 2) . " pendiente",
                'url' => "/dashboard/pagos/{$pago->id}/edit",
                'icon' => '💰',
                'color' => 'info',
                'created_at' => $pago->created_at,
            ];
        }

        // Grupos/moras pendientes
        $morasPendientes = $this->getMorasPendientes();
        
        foreach ($morasPendientes as $mora) {
            $notifications[] = [
                'id' => 'mora_pendiente_' . $mora['id'],
                'type' => 'mora_pendiente',
                'title' => 'Mora pendiente de gestión',
                'description' => "Grupo \"{$mora['grupo']}\" - {$mora['dias_atraso']} días de atraso",
                'url' => "/dashboard/grupos/{$mora['grupo_id']}",
                'icon' => '⚠️',
                'color' => 'danger',
                'created_at' => Carbon::now(),
            ];
        }

        return $notifications;
    }

    /**
     * Obtiene moras pendientes
     */
    private function getMorasPendientes()
    {
        $morasPendientes = [];
        
        // Obtener moras activas (pendientes)
        $moras = Mora::where('estado_mora', 'pendiente')
            ->with(['cuotaGrupal.prestamo.grupo'])
            ->get();

        foreach ($moras as $mora) {
            if ($mora->cuotaGrupal && $mora->cuotaGrupal->prestamo && $mora->cuotaGrupal->prestamo->grupo) {
                $grupo = $mora->cuotaGrupal->prestamo->grupo;
                $diasAtraso = $mora->dias_atraso;
                
                $morasPendientes[] = [
                    'id' => $mora->id,
                    'grupo_id' => $grupo->id,
                    'grupo' => $grupo->nombre_grupo,
                    'dias_atraso' => $diasAtraso,
                ];
            }
        }

        return $morasPendientes;
    }

    /**
     * Obtiene grupos en mora (método legacy - mantenido por compatibilidad)
     */
    private function getGruposEnMora()
    {
        return $this->getMorasPendientes();
    }

    /**
     * Cuenta el total de notificaciones no leídas
     */
    public function getUnreadCount()
    {
        return count($this->getNotifications());
    }

    /**
     * Genera la URL correcta según el tipo de notificación
     */
    public function generateUrl($type, $id)
    {
        switch ($type) {
            case 'prestamo_pendiente':
            case 'prestamo_respuesta':
                return "/dashboard/prestamos/{$id}/edit";
            case 'grupo_mora':
                return "/dashboard/grupos/{$id}";
            case 'pago_pendiente':
                return "/dashboard/pagos/{$id}/edit";
            default:
                return "/dashboard";
        }
    }
}
