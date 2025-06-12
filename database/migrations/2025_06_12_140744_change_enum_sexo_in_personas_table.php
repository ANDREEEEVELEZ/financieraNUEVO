<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::table('personas', function (Blueprint $table) {
            $table->string('sexo', 20)->change();
        });
       DB::table('personas')->where('sexo', 'Hombre')->update(['sexo' => 'Masculino']);
        DB::table('personas')->where('sexo', 'Mujer')->update(['sexo' => 'Femenino']);

        Schema::table('personas', function (Blueprint $table) {
             $table->enum('sexo', ['Masculino', 'Femenino'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('personas', function (Blueprint $table) {
            $table->string('sexo', 20)->change();
        });
        DB::table('personas')->where('sexo', 'Masculino')->update(['sexo' => 'Hombre']);
        DB::table('personas')->where('sexo', 'Femenino')->update(['sexo' => 'Mujer']);

        Schema::table('personas', function (Blueprint $table) {
             $table->enum('sexo', ['Hombre', 'Mujer'])->change();
        });
    }
};
