<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pagos')
            ->whereRaw('BINARY estado_pago != LOWER(estado_pago)')
            ->update(['estado_pago' => DB::raw('LOWER(estado_pago)')]);
    }

    public function down(): void
    {
        // Casing normalization is not reversible.
    }
};
