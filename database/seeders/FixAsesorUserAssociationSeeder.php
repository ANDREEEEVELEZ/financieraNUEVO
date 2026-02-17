<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Asesor;
use App\Models\Persona;

class FixAsesorUserAssociationSeeder extends Seeder
{
    /**
     * Verifica y corrige la asociación entre usuarios Asesor y registros en la tabla asesors.
     */
    public function run(): void
    {
        $this->command->info('=== Verificación y Corrección de Asociación User-Asesor ===');

        // Obtener todos los usuarios con rol Asesor
        $usuariosAsesor = User::whereHas('roles', function ($q) {
            $q->where('name', 'Asesor');
        })->get();

        $this->command->info("\n✓ Usuarios con rol 'Asesor' encontrados: {$usuariosAsesor->count()}");

        foreach ($usuariosAsesor as $user) {
            $this->command->info("\n--- Verificando: {$user->name} ({$user->email}) ---");

            // Buscar registro de Asesor asociado
            $asesor = Asesor::where('user_id', $user->id)->first();

            if ($asesor) {
                $this->command->info("  ✓ Registro Asesor encontrado (ID: {$asesor->id})");

                // Verificar que el asesor tenga persona asociada
                if ($asesor->persona) {
                    $this->command->info("  ✓ Persona asociada: {$asesor->persona->nombre} {$asesor->persona->apellidos}");
                } else {
                    $this->command->warn("  ⚠ ADVERTENCIA: Asesor sin persona asociada");
                }
            } else {
                $this->command->error("  ❌ NO tiene registro en tabla 'asesors'");
                $this->command->info("  → Creando registro de Asesor...");

                // Buscar o crear persona para este asesor
                $persona = $this->buscarOCrearPersona($user);

                // Crear registro de Asesor
                $nuevoAsesor = Asesor::create([
                    'persona_id' => $persona->id,
                    'user_id' => $user->id,
                    'estado_asesor' => 'ACTIVO',
                ]);

                $this->command->info("  ✓ Registro Asesor creado (ID: {$nuevoAsesor->id})");
            }
        }

        $this->command->info("\n=== Proceso Completado ===");
    }

    private function buscarOCrearPersona(User $user): Persona
    {
        // Intentar buscar una persona con el mismo email
        $persona = Persona::where('correo', $user->email)->first();

        if ($persona) {
            $this->command->info("  → Persona existente encontrada con email {$user->email}");
            return $persona;
        }

        // Si no existe, crear una nueva persona basada en los datos del usuario
        $nombreCompleto = $user->name;
        $partes = explode(' ', $nombreCompleto, 2);
        $nombre = $partes[0] ?? 'Asesor';
        $apellidos = $partes[1] ?? 'Sin Apellido';

        $this->command->info("  → Creando nueva persona para el asesor...");

        // Generar DNI único (esto es temporal, debería actualizarse con el DNI real)
        // Usar solo 8 caracteres para cumplir con el límite de la base de datos
        $dniTemporal = str_pad($user->id, 8, '9', STR_PAD_LEFT);

        $persona = Persona::create([
            'DNI' => $dniTemporal,
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'correo' => $user->email,
            'celular' => '000000000', // Valor temporal
            'sexo' => 'M', // Valor por defecto
        ]);

        $this->command->warn("  ⚠ IMPORTANTE: Actualizar datos de persona (DNI: {$dniTemporal})");

        return $persona;
    }
}
