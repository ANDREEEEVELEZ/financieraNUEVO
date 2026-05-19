<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/notifications', function (Request $request) {
        try {
            $notificationService = app(NotificationService::class);
            $notifications = $notificationService->getNotifications();

            $formattedNotifications = array_map(function ($notification) {
                return [
                    'id'          => $notification['id'],
                    'type'        => $notification['type'],
                    'title'       => $notification['title'],
                    'description' => $notification['description'],
                    'url'         => $notification['url'],
                    'icon'        => $notification['icon'],
                    'color'       => $notification['color'],
                    'time'        => $notification['created_at']->diffForHumans(),
                ];
            }, $notifications);

            return response()->json([
                'notifications' => $formattedNotifications,
                'unreadCount'   => count($formattedNotifications),
            ]);
        } catch (\Exception $e) {
            Log::error('Notification API error', [
                'user_id' => Auth::id(),
                'code'    => $e->getCode(),
            ]);

            return response()->json([
                'error'         => 'Error interno del servidor.',
                'notifications' => [],
                'unreadCount'   => 0,
            ], 500);
        }
    });
});
