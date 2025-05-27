><?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up()
    {
        Schema::table('moras', function (Blueprint $table) {
            if (Schema::hasColumn('moras', 'monto_mora')) {
                $table->dropColumn('monto_mora');
            }
        });

        Schema::table('pagos', function (Blueprint $table) {
            if (!Schema::hasColumn('pagos', 'monto_mora_pagada')) {
                $table->decimal('monto_mora_pagada', 10, 2)->nullable()->after('monto_pagado');
            }
        });

        Schema::table('grupos', function (Blueprint $table) {
            if (!Schema::hasColumn('grupos', 'asesor_id')) {
                $table->unsignedBigInteger('asesor_id')->nullable();
            }
        });

        Schema::table('grupos', function (Blueprint $table) {
            $table->foreign('asesor_id')->references('id')->on('asesores')->onDelete('cascade');
        });

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
        Schema::table('moras', function (Blueprint $table) {
            if (!Schema::hasColumn('moras', 'monto_mora')) {
                $table->decimal('monto_mora', 10, 2)->nullable();
            }
        });

        Schema::table('pagos', function (Blueprint $table) {
            if (Schema::hasColumn('pagos', 'monto_mora_pagada')) {
                $table->dropColumn('monto_mora_pagada');
            }
        });

        Schema::table('grupos', function (Blueprint $table) {
            $table->dropForeign(['asesor_id']);
            $table->dropColumn('asesor_id');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign(['asesor_id']);
            $table->dropColumn('asesor_id');
        });
    }
};
