<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Design D8 / spec Scenario 5.1.c: `audit_logs.auditable_type`/`auditable_id`
 * MUST be nullable. Per this project's clean-core convention (no ALTER
 * migration for a pre-prod schema — rewrite the original in place), this
 * rewrites `2026_03_10_000002_create_audit_logs_table.php` directly rather
 * than adding a new migration; `2026_05_26_172145_make_user_id_nullable_in_
 * audit_logs.php` (a separate, pre-existing, unrelated `user_id` ALTER) is
 * left untouched.
 *
 * Runs the ACTUAL migration file's up() against an in-memory SQLite
 * database — never touches the unreachable remote test DB — so this is a
 * genuine schema-level assertion, not a assumption about the file's
 * contents.
 */
uses(Illuminate\Foundation\Testing\TestCase::class);

beforeEach(function () {
    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => ':memory:']);
    DB::purge('sqlite');
});

it('creates auditable_type and auditable_id as nullable columns', function () {
    // `user_id` is `foreignId(...)->constrained()` — needs a `users` table to
    // satisfy SQLite's foreign_key_constraints pragma. Only `id` is needed;
    // this migration's own user_id nullability is out of scope here (a
    // separate, pre-existing migration already handles that).
    Schema::create('users', function (Illuminate\Database\Schema\Blueprint $table) {
        $table->id();
    });
    DB::table('users')->insert(['id' => 1]);

    $migration = require database_path('migrations/2026_03_10_000002_create_audit_logs_table.php');
    $migration->up();

    $columns = collect(Schema::getColumns('audit_logs'))->keyBy('name');

    expect($columns->has('auditable_type'))->toBeTrue();
    expect($columns->has('auditable_id'))->toBeTrue();
    expect($columns['auditable_type']['nullable'])->toBeTrue();
    expect($columns['auditable_id']['nullable'])->toBeTrue();

    // A row with no auditable_type/auditable_id must actually insert cleanly
    // — the load-bearing proof that these columns are genuinely nullable at
    // the schema level, not just per Schema::getColumns()'s metadata.
    DB::table('audit_logs')->insert([
        'user_id' => 1,
        'accion' => 'auth_login_failed',
        'auditable_type' => null,
        'auditable_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('audit_logs')->count())->toBe(1);
});
