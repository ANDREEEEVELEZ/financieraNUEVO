<?php

declare(strict_types=1);

use App\Listeners\Auth\LogFailedLogin;
use App\Listeners\Auth\LogPasswordReset;
use App\Listeners\Auth\LogSuccessfulLogin;
use App\Listeners\Auth\LogSuccessfulLogout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;

/**
 * Design D8 / spec Requirement 5.1: listeners MUST be registered explicitly
 * via `Event::listen()` in `AppServiceProvider::boot()` — no auto-discovery,
 * matching this project's existing "observers are explicit here" convention.
 *
 * Deliberately DB-free: uses the base Illuminate\Foundation\Testing\TestCase
 * (boots the real app via bootstrap/app.php, no RefreshDatabase / role
 * seeding) — event registration never touches the database.
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

it('resolves the exact listener class for each event', function (string $event, string $listener) {
    $listeners = collect(Event::getRawListeners()[$event] ?? []);

    expect($listeners->contains($listener))->toBeTrue();
})->with([
    'Login -> LogSuccessfulLogin' => [Login::class, LogSuccessfulLogin::class],
    'Failed -> LogFailedLogin' => [Failed::class, LogFailedLogin::class],
    'Logout -> LogSuccessfulLogout' => [Logout::class, LogSuccessfulLogout::class],
    'PasswordReset -> LogPasswordReset' => [PasswordReset::class, LogPasswordReset::class],
]);
