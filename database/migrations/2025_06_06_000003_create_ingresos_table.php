<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIngresosTable extends Migration
{
    public function up()
    {
        Schema::create('ingresos', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_ingreso', ['transferencia', 'pago de cuota de grupo']);
            $table->unsignedBigInteger('pago_id')->nullable();
            $table->unsignedBigInteger('grupo_id')->nullable();
            $table->timestamp('fecha_hora');
            $table->text('descripcion');
            $table->decimal('monto', 10, 2);
            $table->timestamps();

            $table->foreign('pago_id')->references('id')->on('pagos')->onDelete('cascade');
            $table->foreign('grupo_id')->references('id')->on('grupos')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ingresos');
    }
}