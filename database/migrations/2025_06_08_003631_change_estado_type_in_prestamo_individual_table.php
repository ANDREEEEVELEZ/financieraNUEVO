<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Cambia el tipo de 'estado' en prestamo_individual a string.
     */
    public function up(): void
    {
        Schema::table('prestamo_individual', function (Blueprint $table) {
             $table->string('estado')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * El down() original tenía un bug: intentaba cambiar 'estado' a decimal(8,2),
     * lo cual es un error — 'estado' siempre fue un campo de texto (string/enum).
     * Se corrige restaurando correctamente a string.
     */
    public function down(): void
    {
        Schema::table('prestamo_individual', function (Blueprint $table) {
            $table->string('estado')->change();
        });
    }
};
