<?php

namespace App\Contracts;

interface NotificationServiceInterface
{
    public function getNotifications();
    public function getUnreadCount();
    public function generateUrl($type, $id);
    public function invalidateNotificationsCache(int $userId): void;
}
