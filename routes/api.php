<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/notifications', function (Request $request) {
        try {
            // Verificar que el usuario esté autenticado
            if (!Auth::check()) {
                return response()->json([
                    'error' => 'Usuario no autenticado',
                    'notifications' => [],
                    'unreadCount' => 0,
                ], 401);
            }

            $notificationService = app(NotificationService::class);
            $notifications = $notificationService->getNotifications();
            
            // Formatear las notificaciones para el frontend
            $formattedNotifications = array_map(function ($notification) {
                return [
                    'id' => $notification['id'],
                    'type' => $notification['type'],
                    'title' => $notification['title'],
                    'description' => $notification['description'],
                    'url' => $notification['url'],
                    'icon' => $notification['icon'],
                    'color' => $notification['color'],
                    'time' => $notification['created_at']->diffForHumans(),
                ];
            }, $notifications);
            
            return response()->json([
                'notifications' => $formattedNotifications,
                'unreadCount' => count($formattedNotifications),
                'debug' => [
                    'user_id' => Auth::id(),
                    'user_roles' => Auth::user()->roles ? Auth::user()->roles->pluck('name')->toArray() : [],
                    'total_notifications' => count($notifications),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en API de notificaciones: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => $e->getMessage(),
                'notifications' => [],
                'unreadCount' => 0,
            ], 500);
        }
    });

    // Endpoint de prueba temporal
    Route::get('/notifications-debug', function (Request $request) {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado',
                    'auth_check' => Auth::check(),
                    'session_id' => session()->getId(),
                ], 401);
            }
            
            // Información básica del usuario
            $userInfo = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_roles' => $user->roles ? $user->roles->pluck('name')->toArray() : [],
                'auth_guard' => config('auth.defaults.guard'),
            ];
            
            // Contar préstamos por estado
            $prestamosCount = \App\Models\Prestamo::select('estado')
                ->selectRaw('count(*) as total')
                ->groupBy('estado')
                ->get()
                ->keyBy('estado')
                ->map->total
                ->toArray();
                
            // Contar pagos por estado
            $pagosCount = \App\Models\Pago::select('estado_pago')
                ->selectRaw('count(*) as total')
                ->groupBy('estado_pago')
                ->get()
                ->keyBy('estado_pago')
                ->map->total
                ->toArray();
                
            // Contar moras por estado
            $morasCount = \App\Models\Mora::select('estado_mora')
                ->selectRaw('count(*) as total')
                ->groupBy('estado_mora')
                ->get()
                ->keyBy('estado_mora')
                ->map->total
                ->toArray();
            
            // Probar el servicio
            $notificationService = app(NotificationService::class);
            $notifications = $notificationService->getNotifications();
            
            // Info del servidor
            $serverInfo = [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment(),
                'app_url' => config('app.url'),
                'current_url' => $request->url(),
            ];
            
            return response()->json([
                'status' => 'success',
                'timestamp' => now()->toISOString(),
                'user_info' => $userInfo,
                'server_info' => $serverInfo,
                'database_counts' => [
                    'prestamos' => $prestamosCount,
                    'pagos' => $pagosCount,
                    'moras' => $morasCount,
                ],
                'notifications' => [
                    'count' => count($notifications),
                    'data' => $notifications,
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en debug endpoint:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    });
});
