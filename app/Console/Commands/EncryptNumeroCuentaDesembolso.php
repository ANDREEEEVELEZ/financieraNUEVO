<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Slice 3 (PII encryption, Requirement 3.2) — one-off backfill for existing
 * `prestamos.numero_cuenta_desembolso` rows, run once per environment after
 * deploying the `'encrypted'` cast (Prestamo::$casts) and the widened `text`
 * column (see database/migrations/2025_05_10_035228_create_prestamos_table.php).
 *
 * Deliberately NOT a migration (per explicit instruction: this is pre-prod,
 * schema changes are rewritten in place, and a real backfill of existing rows
 * is an operational step, not a schema change). Uses the raw query builder
 * throughout (never the Eloquent model) so it never triggers a decrypt
 * attempt on a still-plaintext row via the model's cast.
 *
 * Idempotent: each row is probed with Crypt::decryptString() first — if it
 * already decrypts cleanly, the row is already encrypted and is skipped, so
 * running this command twice (or against a partially-migrated environment)
 * is safe and will not double-encrypt.
 *
 * Requirement 3.2.d (operational precondition): a verified DB backup MUST
 * exist before running this in production.
 */
class EncryptNumeroCuentaDesembolso extends Command
{
    protected $signature = 'prestamos:encrypt-numero-cuenta-desembolso';

    protected $description = 'Backfill: encrypt existing plaintext prestamos.numero_cuenta_desembolso values in place (Slice 3, Requirement 3.2)';

    public function handle(): int
    {
        $this->info('Iniciando backfill de numero_cuenta_desembolso...');

        $total = 0;
        $encrypted = 0;
        $alreadyEncrypted = 0;

        DB::table('prestamos')
            ->whereNotNull('numero_cuenta_desembolso')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$total, &$encrypted, &$alreadyEncrypted) {
                DB::transaction(function () use ($rows, &$total, &$encrypted, &$alreadyEncrypted) {
                    foreach ($rows as $row) {
                        $total++;

                        if ($this->isAlreadyEncrypted($row->numero_cuenta_desembolso)) {
                            $alreadyEncrypted++;

                            continue;
                        }

                        DB::table('prestamos')
                            ->where('id', $row->id)
                            ->update([
                                'numero_cuenta_desembolso' => Crypt::encryptString($row->numero_cuenta_desembolso),
                            ]);

                        $encrypted++;
                    }
                });
            });

        $this->info('Backfill completado:');
        $this->info("- {$total} filas revisadas");
        $this->info("- {$encrypted} filas cifradas");
        $this->info("- {$alreadyEncrypted} filas ya estaban cifradas (omitidas)");

        return self::SUCCESS;
    }

    private function isAlreadyEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
