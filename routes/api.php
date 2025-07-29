<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Services\NotificationService;

Route::middleware('auth:web')->group(function () {
    Route::get('/notifications', function (Request $request) {
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
        ]);
    });

    // Endpoint de prueba temporal
    Route::get('/notifications-debug', function (Request $request) {
        $user = $request->user();
        
        // Información básica del usuario
        $userInfo = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_roles' => $user->getRoleNames()->toArray(),
        ];
        
        // Contar préstamos por estado
        $prestamosCount = \App\Models\Prestamo::select('estado')
            ->selectRaw('count(*) as total')
            ->groupBy('estado')
            ->get()
            ->keyBy('estado')
            ->map->total
            ->toArray();
        
        // Probar el servicio
        $notificationService = app(NotificationService::class);
        $notifications = $notificationService->getNotifications();
        
        return response()->json([
            'user_info' => $userInfo,
            'prestamos_count' => $prestamosCount,
            'notifications_count' => count($notifications),
            'notifications' => $notifications,
        ]);
    });
});
