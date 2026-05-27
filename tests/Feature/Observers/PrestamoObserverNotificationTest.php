<?php

use App\Contracts\NotificationServiceInterface;
use App\Observers\PrestamoObserver;

test('PrestamoObserver constructor accepts NotificationServiceInterface as second parameter', function () {
    $reflection = new ReflectionClass(PrestamoObserver::class);
    $constructor = $reflection->getConstructor();

    $params = $constructor->getParameters();
    expect(count($params))->toBeGreaterThanOrEqual(2);

    $secondParam = $params[1];
    $type = $secondParam->getType();

    expect($type)->not()->toBeNull();
    expect($type->getName())->toBe(NotificationServiceInterface::class);
});

test('PrestamoObserver can be resolved from container with NotificationServiceInterface injected', function () {
    $observer = app(PrestamoObserver::class);

    $reflection = new ReflectionClass($observer);
    $property = $reflection->getProperty('notifications');
    $property->setAccessible(true);

    $notifications = $property->getValue($observer);
    expect($notifications)->toBeInstanceOf(NotificationServiceInterface::class);
});
