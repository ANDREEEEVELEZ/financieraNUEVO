<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->string('titular_cuenta_desembolso')->nullable()->after('estado');
            $table->string('numero_cuenta_desembolso')->nullable()->after('titular_cuenta_desembolso');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropColumn(['titular_cuenta_desembolso', 'numero_cuenta_desembolso']);
        });
    }
};
