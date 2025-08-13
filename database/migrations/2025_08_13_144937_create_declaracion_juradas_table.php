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
        Schema::create('declaracion_juradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade');
            
            // Datos automáticos del cliente (no editables)
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('dni');
            $table->string('nacionalidad')->default('PERUANA');
            $table->string('estado_civil');
            $table->string('conyuge_nombres')->nullable();
            $table->string('domicilio');
            $table->string('distrito');
            $table->string('provincia')->default('SULLANA');
            $table->string('departamento')->default('PIURA');
            $table->string('ocupacion');
            $table->string('telefono_fijo')->nullable();
            $table->string('celular');
            $table->string('correo');
            
            // Campos configurables
            $table->enum('tipo_documento', ['DNI', 'Pasaporte', 'Carnet_Extranjeria', 'Otro'])->default('DNI');
            $table->string('numero_documento');
            $table->string('otro_documento_detalle')->nullable();
            
            // Campos de domicilio detallados
            $table->string('tipo_via')->default('Jr.');
            $table->string('nombre_via');
            $table->string('urbanizacion')->nullable();
            $table->string('complejo_zona_sector')->nullable();
            $table->string('interior')->nullable();
            $table->string('departamento_numero')->nullable();
            
            // Propósito de relación comercial (punto 9)
            $table->enum('proposito_relacion', ['Prestamo'])->default('Prestamo');
            
            // Campos PEP (punto 10)
            $table->enum('es_pep', ['NO_SOY', 'NO_HE_SIDO', 'SOY', 'HE_SIDO'])->default('NO_SOY');
            $table->string('cargo_publico')->nullable();
            $table->string('entidad_publica')->nullable();
            $table->date('fecha_inicio_cargo')->nullable();
            $table->date('fecha_fin_cargo')->nullable();
            $table->text('parentesco_pep')->nullable();
            
            // Campos de representación (punto 11)
            $table->enum('actua_por', ['MI_MISMO', 'TERCERA_PERSONA'])->default('MI_MISMO');
            $table->string('representado_nombres')->nullable();
            $table->string('representado_apellidos')->nullable();
            $table->string('representado_documento')->nullable();
            
            // Metadatos
            $table->timestamp('fecha_declaracion')->useCurrent();
            $table->string('lugar_declaracion')->default('Sullana');
            $table->json('datos_adicionales')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('declaracion_juradas');
    }
};
