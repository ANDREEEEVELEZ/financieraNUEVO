><?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up()
    {
        // Eliminar monto_mora en moras
        Schema::table('moras', function (Blueprint $table) {
            if (Schema::hasColumn('moras', 'monto_mora')) {
                $table->dropColumn('monto_mora');
            }
        });

        // Agregar monto_mora_pagada a pagos
        Schema::table('pagos', function (Blueprint $table) {
            if (!Schema::hasColumn('pagos', 'monto_mora_pagada')) {
                $table->decimal('monto_mora_pagada', 10, 2)->nullable()->after('monto_pagado');
            }
        });

        // Agregar asesor_id a grupos sin llave
        Schema::table('grupos', function (Blueprint $table) {
            if (!Schema::hasColumn('grupos', 'asesor_id')) {
                $table->unsignedBigInteger('asesor_id')->nullable();
            }
        });

        // Luego agregar llave foránea a grupos
        Schema::table('grupos', function (Blueprint $table) {
            $table->foreign('asesor_id')->references('id')->on('asesores')->onDelete('cascade');
        });

        // Agregar asesor_id a clientes (ya funciona para ti)
        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'asesor_id')) {
                $table->foreignId('asesor_id')
                      ->nullable()
                      ->constrained('asesores')
                      ->onDelete('set null');
            }
        });
    }

    public function down()
    {
        // Agregar monto_mora a moras
        Schema::table('moras', function (Blueprint $table) {
            if (!Schema::hasColumn('moras', 'monto_mora')) {
                $table->decimal('monto_mora', 10, 2)->nullable();
            }
        });

        // Eliminar monto_mora_pagada en pagos
        Schema::table('pagos', function (Blueprint $table) {
            if (Schema::hasColumn('pagos', 'monto_mora_pagada')) {
                $table->dropColumn('monto_mora_pagada');
            }
        });

        // Eliminar foreign key y columna asesor_id de grupos
        Schema::table('grupos', function (Blueprint $table) {
            $table->dropForeign(['asesor_id']);
            $table->dropColumn('asesor_id');
        });

        // Eliminar foreign key y columna asesor_id de clientes
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign(['asesor_id']);
            $table->dropColumn('asesor_id');
        });
    }
};
