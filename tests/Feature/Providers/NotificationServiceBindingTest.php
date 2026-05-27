<?php

use App\Contracts\NotificationServiceInterface;
use App\Infrastructure\Notifications\NotificationService;

test('NotificationServiceInterface resolves to NotificationService', function () {
    $resolved = app(NotificationServiceInterface::class);

    expect($resolved)->toBeInstanceOf(NotificationService::class);
});

test('NotificationServiceInterface is bound as a singleton', function () {
    $first = app(NotificationServiceInterface::class);
    $second = app(NotificationServiceInterface::class);

    expect($first)->toBe($second);
});
