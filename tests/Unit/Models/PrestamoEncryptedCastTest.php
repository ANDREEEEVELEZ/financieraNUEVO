<?php

/**
 * Slice 3 (PII encryption, Requirement 3.2) — Scenario 3.2.a: new records store
 * `numero_cuenta_desembolso` encrypted at rest and decrypt transparently through
 * Eloquent.
 *
 * Deliberately isolated from the shared MySQL connection (which is unreachable
 * from this sandbox — see apply-progress for PR2/PR3 findings): this test spins
 * up its own in-memory SQLite connection and runs the actual, rewritten
 * `2025_05_10_035228_create_prestamos_table` migration against it, so it
 * exercises the real migration file (task 3.6 rewrites the column definition
 * in place) rather than a hand-rolled schema. `foreign_key_constraints` is
 * disabled on this connection because the migration's `grupo_id`/`producto_id`
 * foreign keys target tables this test never creates — irrelevant to what is
 * being verified here (the `numero_cuenta_desembolso` cast/column behavior).
 */

use App\Models\Prestamo;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(BaseTestCase::class);

beforeEach(function () {
    config(['database.connections.sqlite_prestamo_test' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]]);
    config(['database.default' => 'sqlite_prestamo_test']);
    DB::purge('sqlite_prestamo_test');

    $migration = require database_path('migrations/2025_05_10_035228_create_prestamos_table.php');
    $migration->up();
});

afterEach(function () {
    Schema::dropIfExists('prestamos');
});

it('stores numero_cuenta_desembolso encrypted at rest and decrypts transparently via Eloquent', function () {
    $plaintext = '012-345678';

    // grupo_id is NOT NULL on prestamos; foreign_key_constraints is disabled on
    // this test connection, so a dangling reference (no `grupos` table exists
    // here at all) is accepted — irrelevant to what this test verifies.
    // withoutEvents(): PrestamoObserver::created() invalidates caches via
    // CacheServiceInterface, which resolves Spatie roles against the DB —
    // unrelated to the cast behavior under test and not present on this
    // isolated connection.
    $prestamo = Prestamo::withoutEvents(
        fn () => Prestamo::create(['grupo_id' => 1, 'numero_cuenta_desembolso' => $plaintext])
    );

    $rawValue = DB::table('prestamos')->find($prestamo->id)->numero_cuenta_desembolso;

    expect($rawValue)->not->toBe($plaintext);
    expect(Prestamo::find($prestamo->id)->numero_cuenta_desembolso)->toBe($plaintext);
});

it('round-trips a long account number without truncation once the column is widened', function () {
    // 40 chars: encrypt() of a value this size produces ~288 bytes of ciphertext,
    // which would overflow the original VARCHAR(255) definition (measured via
    // `encrypt(str_repeat('9', 40))` during the Slice 3 audit — see apply-progress).
    $plaintext = str_repeat('9', 40);

    $prestamo = Prestamo::withoutEvents(
        fn () => Prestamo::create(['grupo_id' => 1, 'numero_cuenta_desembolso' => $plaintext])
    );

    expect(Prestamo::find($prestamo->id)->numero_cuenta_desembolso)->toBe($plaintext);
});
