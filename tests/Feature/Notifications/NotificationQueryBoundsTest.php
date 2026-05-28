<?php

use App\Infrastructure\Notifications\NotificationService;
use App\Models\Pago;
use App\Models\Prestamo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

// ── T-2.1: Supervisor notifications are bounded ────────────────────────────

it('getSupervisorNotifications returns at most 20 pago_pendiente notifications', function () {
    // Create roles and a supervisor user
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = \App\Models\User::factory()->create();
    $user->assignRole('super_admin');

    // Create 25 pending pagos via an active prestamo (Pago model enforces prestamo state)
    $prestamo = Prestamo::factory()->create(['estado' => 'Activo']);
    $cuotas = \App\Models\CuotasGrupales::factory()->count(25)->create([
        'prestamo_id' => $prestamo->id,
        'estado_pago' => 'pendiente',
    ]);
    $cuotas->each(fn($cuota) => Pago::factory()->create([
        'cuota_grupal_id' => $cuota->id,
        'estado_pago' => 'pendiente',
    ]));

    $this->actingAs($user);

    $service = app(NotificationService::class);
    $notifications = $service->getNotifications();

    $pagoPendienteNotifs = array_filter($notifications, fn($n) => $n['type'] === 'pago_pendiente');

    expect(count($pagoPendienteNotifs))->toBeLessThanOrEqual(20,
        'getSupervisorNotifications must not return more than 20 pago_pendiente notifications'
    );
});

// ── Triangulation: No debug log fires ─────────────────────────────────────

it('getSupervisorNotifications does not fire debug Log::info with prestamos por estado', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = \App\Models\User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user);

    $infoMessages = [];
    Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) use (&$infoMessages) {
        if ($event->level === 'info') {
            $infoMessages[] = $event->message;
        }
    });

    $service = app(NotificationService::class);
    $service->getNotifications();

    $debugMessages = array_filter($infoMessages, fn($msg) => str_contains($msg, 'Préstamos por estado'));

    expect(count($debugMessages))->toBe(0,
        'The debug Log::info("Préstamos por estado") must be removed from getSupervisorNotifications'
    );
});

// ── Triangulation: No TEMPORAL test notifications appear ──────────────────

it('getSupervisorNotifications does not return TEMPORAL test notifications', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = \App\Models\User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user);

    $service = app(NotificationService::class);
    $notifications = $service->getNotifications();

    $testNotifs = array_filter($notifications, fn($n) => $n['type'] === 'test');

    expect(count($testNotifs))->toBe(0,
        'TEMPORAL test notifications must be removed from getSupervisorNotifications'
    );
});

// ── Triangulation: Cache TTL is 300 seconds ───────────────────────────────

it('getNotifications cache TTL is 300 seconds', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = \App\Models\User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user);

    $capturedTtl = null;

    Cache::shouldReceive('remember')
        ->once()
        ->withArgs(function ($key, $ttl, $callback) use (&$capturedTtl, $user) {
            if ($key === 'ec_notifications_user_' . $user->id) {
                $capturedTtl = $ttl;
                $callback(); // invoke so the rest of the test doesn't fail
                return true;
            }
            return false;
        })
        ->andReturnUsing(fn($key, $ttl, $cb) => $cb());

    $service = app(NotificationService::class);
    $service->getNotifications();

    expect($capturedTtl)->toBe(300,
        'Notification cache TTL must be 300 seconds (was 60 before the fix)'
    );
});
