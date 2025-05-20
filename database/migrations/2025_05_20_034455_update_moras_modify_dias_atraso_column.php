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
        Schema::table('moras', function (Blueprint $table) {
            $table->date('fecha_atraso')->nullable();
            $table->dropColumn('dias_atraso');
            //
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moras', function (Blueprint $table) {
             $table->dropColumn('fecha_atraso');
            $table->date('dias_atraso');
        });
    }
};
