<?php

/**
 * Slice 3 (PII encryption, Requirement 3.2) — backfill command.
 *
 * Verifies `prestamos:encrypt-numero-cuenta-desembolso`:
 *  - encrypts an existing plaintext row in place (Scenario 3.2.b's intent,
 *    delivered as an operational Artisan command rather than a migration
 *    per explicit instruction — see apply-progress);
 *  - is idempotent: running it twice does not double-encrypt an already
 *    encrypted value (a second decrypt of double-ciphertext would not
 *    equal the original plaintext, which is exactly the corruption this
 *    guards against).
 *
 * Isolated in-memory SQLite connection, same rationale as
 * PrestamoEncryptedCastTest — the shared MySQL connection is unreachable
 * from this sandbox.
 */

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(BaseTestCase::class);

beforeEach(function () {
    config(['database.connections.sqlite_backfill_test' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]]);
    config(['database.default' => 'sqlite_backfill_test']);
    DB::purge('sqlite_backfill_test');

    $migration = require database_path('migrations/2025_05_10_035228_create_prestamos_table.php');
    $migration->up();
});

afterEach(function () {
    Schema::dropIfExists('prestamos');
});

it('encrypts an existing plaintext row in place', function () {
    $plaintext = '012-345678';

    $id = DB::table('prestamos')->insertGetId([
        'grupo_id' => 1,
        'numero_cuenta_desembolso' => $plaintext,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('prestamos:encrypt-numero-cuenta-desembolso');

    $raw = DB::table('prestamos')->find($id)->numero_cuenta_desembolso;

    expect($raw)->not->toBe($plaintext);
    expect(Crypt::decryptString($raw))->toBe($plaintext);
});

it('is idempotent — running twice does not double-encrypt', function () {
    $plaintext = '987-654321';

    $id = DB::table('prestamos')->insertGetId([
        'grupo_id' => 1,
        'numero_cuenta_desembolso' => $plaintext,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('prestamos:encrypt-numero-cuenta-desembolso');
    $firstPass = DB::table('prestamos')->find($id)->numero_cuenta_desembolso;

    Artisan::call('prestamos:encrypt-numero-cuenta-desembolso');
    $secondPass = DB::table('prestamos')->find($id)->numero_cuenta_desembolso;

    expect($secondPass)->toBe($firstPass);
    expect(Crypt::decryptString($secondPass))->toBe($plaintext);
});

it('leaves null values untouched', function () {
    $id = DB::table('prestamos')->insertGetId([
        'grupo_id' => 1,
        'numero_cuenta_desembolso' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Artisan::call('prestamos:encrypt-numero-cuenta-desembolso');

    expect(DB::table('prestamos')->find($id)->numero_cuenta_desembolso)->toBeNull();
});
