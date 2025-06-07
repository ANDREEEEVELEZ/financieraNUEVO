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
        Schema::table('egresos', function (Blueprint $table) {
            $table->dropColumn('detalle_subcategoria');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('egresos', function (Blueprint $table) {
             $table->text('detalle_subcategoria')->nullable();
        });
    }
};
