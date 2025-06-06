<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEgresosTable extends Migration
{
    public function up()
    {
        Schema::create('egresos', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_egreso', ['gasto', 'desembolso']);
            $table->date('fecha');
            $table->text('descripcion');
            $table->decimal('monto', 10, 2);
            $table->unsignedBigInteger('prestamo_id')->nullable();
            $table->unsignedBigInteger('categoria_id')->nullable();
            $table->unsignedBigInteger('subcategoria_id')->nullable();
            $table->text('detalle_subcategoria')->nullable();
            $table->timestamps();

            $table->foreign('prestamo_id')->references('id')->on('prestamos')->onDelete('cascade');
            $table->foreign('categoria_id')->references('id')->on('categorias')->onDelete('cascade');
            $table->foreign('subcategoria_id')->references('id')->on('subcategorias')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('egresos');
    }
}
