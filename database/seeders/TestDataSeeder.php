<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Asesor;
use App\Models\Persona;
use App\Models\Cliente;
use App\Models\Grupo;
use App\Models\Prestamo;
use App\Models\PrestamoIndividual;
use App\Models\ProductoFinanciero;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // 0. Limpieza (Buenas prácticas para entornos de DEV)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Persona::truncate();
        Cliente::truncate();
        Grupo::truncate();
        Prestamo::truncate();
        PrestamoIndividual::truncate();
        DB::table('grupo_cliente')->truncate();
        DB::table('cuotas_grupales')->truncate();
        DB::table('cuota_individual')->truncate();
        DB::table('movimiento_financiero')->truncate();
        DB::table('egresos')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $asesorUser = User::where('email', 'asesor@test.com')->first();
        if (!$asesorUser) {
            $this->command->error('No se encontró el usuario asesor@test.com. Ejecutá DevelopmentUsersSeeder primero.');
            return;
        }
        $asesor = Asesor::where('user_id', $asesorUser->id)->first();

        // 1. Crear Producto Financiero
        $producto = ProductoFinanciero::updateOrCreate(
            ['codigo' => 'CG-001'],
            [
                'nombre' => 'Crédito Grupal Estándar',
                'tipo' => 'GRUPAL',
                'tasa_interes' => 5.0, // 5% mensual
                'tasa_mora' => 1.0,    // 1% diario
                'permite_condonacion_mora' => true,
                'monto_minimo' => 1000,
                'monto_maximo' => 50000,
                'plazo_minimo_meses' => 3,
                'plazo_maximo_meses' => 24,
                'activo' => true
            ]
        );

        // 2. Crear 20 Clientes
        $clientes = [];
        for ($i = 1; $i <= 20; $i++) {
            $numText = $this->numberToText($i);
            $persona = Persona::create([
                'DNI' => str_pad($i, 8, '0', STR_PAD_LEFT),
                'nombre' => "Cliente $numText",
                'apellidos' => "Test",
                'correo' => "cliente{$i}@test.com",
                'celular' => '9' . str_pad($i, 8, '0', STR_PAD_LEFT),
                'direccion' => "Dirección Cliente $i",
            ]);

            $clientes[] = Cliente::create([
                'persona_id' => $persona->id,
                'asesor_id' => $asesor->id,
                'ciclo' => 1,
                'estado_cliente' => 'activo',
                'infocorp' => 'normal',
                'condicion_vivienda' => 'propia',
                'actividad' => 'comercio'
            ]);
        }

        // 3. Crear 3 Grupos y asignar 5 integrantes a cada uno
        $grupos = [];
        $clienteIndex = 0;
        for ($g = 1; $g <= 3; $g++) {
            $grupo = Grupo::create([
                'nombre_grupo' => "Grupo Test $g",
                'numero_integrantes' => 5,
                'fecha_registro' => now(),
                'estado_grupo' => 'activo',
                'asesor_id' => $asesor->id,
                'calificacion_grupo' => 'A'
            ]);
            $grupos[] = $grupo;

            // Asignar 5 integrantes vía tabla pivote
            for ($j = 0; $j < 5; $j++) {
                DB::table('grupo_cliente')->insert([
                    'grupo_id' => $grupo->id,
                    'cliente_id' => $clientes[$clienteIndex]->id,
                    'fecha_ingreso' => now(),
                    'rol' => ($j === 0) ? 'presidente' : 'integrante',
                    'estado_grupo_cliente' => 'activo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $clienteIndex++;
            }
        }

        // 4. Crear un Préstamo Grupal para cada grupo
        foreach ($grupos as $index => $grupo) {
            $montoIndividual = 2000;
            $montoTotal = $montoIndividual * 5;
            $interesTotal = $montoTotal * 0.15; 

            // Crear el préstamo inicialmente como Pendiente
            $prestamo = Prestamo::create([
                'grupo_id' => $grupo->id,
                'producto_id' => $producto->id,
                'tasa_interes' => 5.0,
                'monto_prestado_total' => $montoTotal,
                'monto_devolver' => $montoTotal + $interesTotal,
                'cantidad_cuotas' => 12,
                'fecha_prestamo' => now()->subDays(10),
                'frecuencia' => 'mensual',
                'estado' => 'Pendiente',
                'descripcion' => "Préstamo de prueba para {$grupo->nombre_grupo}",
                'titular_cuenta_desembolso' => "Presidente {$grupo->nombre_grupo}",
                'numero_cuenta_desembolso' => "0011-0223-".str_pad($index, 8, '0', STR_PAD_LEFT)
            ]);

            // Crear detalles individuales
            $integrantesIds = DB::table('grupo_cliente')
                ->where('grupo_id', $grupo->id)
                ->pluck('cliente_id');

            foreach ($integrantesIds as $clienteId) {
                PrestamoIndividual::create([
                    'prestamo_id' => $prestamo->id,
                    'cliente_id' => $clienteId,
                    'monto_prestado_individual' => $montoIndividual,
                    'monto_cuota_prestamo_individual' => ($montoIndividual * 1.15) / 12,
                    'monto_devolver_individual' => $montoIndividual * 1.15,
                    'seguro' => 10.0,
                    'interes' => $montoIndividual * 0.15,
                    'estado' => 'Pendiente'
                ]);
            }

            // SIMULAR WORKFLOW para disparar el Observer (Generación de Cuotas)
            // 1. Pendiente -> Aprobado
            $prestamo->update(['estado' => 'Aprobado']);
            // 2. Aprobado -> Por Desembolsar (El observer lo hace automático a veces, pero aseguramos)
            $prestamo->update(['estado' => 'Por Desembolsar']);
            // 3. Por Desembolsar -> Desembolsado (Esto dispara procesarDesembolso en el Observer)
            $prestamo->update([
                'estado' => 'Desembolsado',
                'fecha_desembolso' => now()->subDays(5)
            ]);
            
            // 4. Finalmente lo pasamos a Activo para que sea cobrable
            $prestamo->update(['estado' => 'Activo']);
            $prestamo->prestamoIndividual()->update(['estado' => 'Activo']);
        }

        $this->command->info('✓ Test data generado: 20 clientes, 3 grupos, 3 préstamos activos.');
    }

    private function numberToText($n): string
    {
        $map = [1=>'Uno', 2=>'Dos', 3=>'Tres', 4=>'Cuatro', 5=>'Cinco', 6=>'Seis', 7=>'Siete', 8=>'Ocho', 9=>'Nueve', 10=>'Diez',
                11=>'Once', 12=>'Doce', 13=>'Trece', 14=>'Catorce', 15=>'Quince', 16=>'Dieciséis', 17=>'Diecisiete', 18=>'Dieciocho', 19=>'Diecinueve', 20=>'Veinte'];
        return $map[$n] ?? (string)$n;
    }
}
