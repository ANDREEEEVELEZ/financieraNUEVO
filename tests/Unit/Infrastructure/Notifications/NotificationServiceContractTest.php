<?php

use App\Contracts\NotificationServiceInterface;
use App\Infrastructure\Notifications\NotificationService;

it('NotificationService implements NotificationServiceInterface', function () {
    expect(
        is_a(NotificationService::class, NotificationServiceInterface::class, true)
    )->toBeTrue();
});

it('invalidateNotificationsCache is not a static method', function () {
    $reflection = new ReflectionMethod(NotificationService::class, 'invalidateNotificationsCache');
    expect($reflection->isStatic())->toBeFalse();
});
