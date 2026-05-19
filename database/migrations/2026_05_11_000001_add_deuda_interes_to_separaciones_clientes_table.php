<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('separaciones_clientes', function (Blueprint $table) {
            $table->decimal('deuda_interes', 12, 2)->default(0)
                ->after('deuda_capital')
                ->comment('Deuda de interés pendiente del cliente al momento de la separación');
        });
    }

    public function down(): void
    {
        Schema::table('separaciones_clientes', function (Blueprint $table) {
            $table->dropColumn('deuda_interes');
        });
    }
};
