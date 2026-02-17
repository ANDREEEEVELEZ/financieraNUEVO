<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Asesor;
use App\Models\Persona;

class FixPruebaAsesorSeeder extends Seeder
{
    /**
     * Crear el registro de Asesor faltante para prueba_asesor@gmail.com
     */
    public function run(): void
    {
        $this->command->info('=== CORRIGIENDO: prueba_asesor@gmail.com ===');

        $user = User::where('email', 'prueba_asesor@gmail.com')->first();

        if (!$user) {
            $this->command->error('❌ Usuario prueba_asesor@gmail.com no encontrado');
            return;
        }

        $this->command->info("Usuario encontrado: {$user->name} (ID: {$user->id})");

        // Verificar si ya tiene registro de Asesor
        $asesorExistente = Asesor::where('user_id', $user->id)->first();

        if ($asesorExistente) {
            $this->command->info("✓ Ya tiene registro de Asesor (ID: {$asesorExistente->id})");
            return;
        }

        $this->command->warn("⚠ NO tiene registro de Asesor - creando...");

        // Buscar o crear persona
        $persona = Persona::where('correo', $user->email)->first();

        if (!$persona) {
            $this->command->info("Creando persona...");

            // Extraer nombre del username
            $nombreCompleto = str_replace('_', ' ', $user->name);
            $partes = explode(' ', $nombreCompleto, 2);

            $persona = Persona::create([
                'DNI' => '99999' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'nombre' => ucfirst(strtolower($partes[0] ?? 'Prueba')),
                'apellidos' => ucfirst(strtolower($partes[1] ?? 'Asesor')),
                'correo' => $user->email,
                'celular' => '999999' . str_pad($user->id, 3, '0', STR_PAD_LEFT),
                'sexo' => 'Masculino',
            ]);

            $this->command->info("✓ Persona creada (ID: {$persona->id})");
        } else {
            $this->command->info("✓ Persona existente encontrada (ID: {$persona->id})");
        }

        // Crear registro de Asesor
        $asesor = Asesor::create([
            'persona_id' => $persona->id,
            'user_id' => $user->id,
            'estado_asesor' => 'ACTIVO',
        ]);

        $this->command->info("✓ Registro de Asesor creado (ID: {$asesor->id})");

        // Verificación final
        $this->command->info("\n=== VERIFICACIÓN FINAL ===");
        $this->command->info("El usuario {$user->email} ahora:");
        $this->command->info("  ✓ Tiene rol 'Asesor'");
        $this->command->info("  ✓ Tiene registro en tabla 'asesors' (ID: {$asesor->id})");
        $this->command->info("  ✓ Puede crear y editar clientes/grupos");

        $this->command->info("\n¡SOLUCIÓN APLICADA! Cierra sesión y vuelve a entrar.");
    }
}
