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
            $table->decimal('monto_prestado_total', 10, 2)->change();
            $table->decimal('monto_devolver', 10, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
             $table->string('monto_prestado_total', 255)->change();
            $table->string('monto_devolver', 255)->change();
        });
    }
};
