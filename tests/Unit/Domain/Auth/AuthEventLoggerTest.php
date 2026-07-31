<?php

declare(strict_types=1);

use App\Domain\Auth\AuthEventLogger;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Design D8 / spec Scenario 5.1.c: `AuthEventLogger` writes `AuditLog` rows
 * directly and MUST tolerate a null $modelo (nullable `auditable_type`/
 * `auditable_id`).
 *
 * The remote test DB (`metro.proxy.rlwy.net:13114`) is unreachable in this
 * sandbox (standing constraint since PR2, see apply-progress). Rather than
 * accept no DB-backed coverage at all for the one genuinely novel piece of
 * this slice (the nullable-auditable write path), this test switches the
 * default connection to an in-memory SQLite database and creates a minimal
 * `audit_logs` table matching the rewritten migration's shape — fully
 * self-contained, never touches the remote DB.
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

beforeEach(function () {
    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => ':memory:']);
    DB::purge('sqlite');

    Schema::create('audit_logs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('accion', 100);
        $table->string('auditable_type')->nullable();
        $table->unsignedBigInteger('auditable_id')->nullable();
        $table->json('datos_anteriores')->nullable();
        $table->json('datos_nuevos')->nullable();
        $table->text('motivo')->nullable();
        $table->string('ip', 45)->nullable();
        $table->timestamps();
    });
});

it('writes a row referencing the model when one is resolved', function () {
    $user = new User();
    $user->id = 99;

    (new AuthEventLogger())->log('auth_login', $user, ['guard' => 'web']);

    $row = AuditLog::first();

    expect($row)->not->toBeNull();
    expect($row->accion)->toBe('auth_login');
    expect($row->auditable_type)->toBe(User::class);
    expect((int) $row->auditable_id)->toBe(99);
    expect($row->datos_nuevos)->toBe(['guard' => 'web']);
});

it('writes a row with null auditable_type/auditable_id when no model is resolved', function () {
    (new AuthEventLogger())->log('auth_login_failed', null, ['email_attempted' => 'ghost@example.com']);

    $row = AuditLog::first();

    expect($row)->not->toBeNull();
    expect($row->accion)->toBe('auth_login_failed');
    expect($row->auditable_type)->toBeNull();
    expect($row->auditable_id)->toBeNull();
    expect($row->datos_nuevos['email_attempted'])->toBe('ghost@example.com');
});
