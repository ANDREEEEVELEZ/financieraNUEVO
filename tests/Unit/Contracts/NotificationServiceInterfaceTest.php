<?php

use App\Contracts\NotificationServiceInterface;

it('NotificationServiceInterface exists and is an interface', function () {
    expect(interface_exists(NotificationServiceInterface::class))->toBeTrue();

    $reflection = new ReflectionClass(NotificationServiceInterface::class);
    expect($reflection->isInterface())->toBeTrue();
});

it('NotificationServiceInterface declares exactly the 4 required methods', function () {
    $reflection = new ReflectionClass(NotificationServiceInterface::class);

    $required = [
        'getNotifications',
        'getUnreadCount',
        'generateUrl',
        'invalidateNotificationsCache',
    ];

    foreach ($required as $method) {
        expect($reflection->hasMethod($method))
            ->toBeTrue("Interface must declare method: {$method}");
    }

    // Assert no other public methods are declared
    $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    expect(count($publicMethods))->toBe(4);
});
