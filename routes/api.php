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
});
