<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\Persona;
use Illuminate\Support\Facades\Auth;

class TestAsesorCreateClienteSeeder extends Seeder
{
    /**
     * Simula el proceso de crear un cliente como Asesor para identificar el problema.
     */
    public function run(): void
    {
        $this->command->info('=== Test: Asesor Creando Cliente ===');

        // Obtener el usuario asesor
        $user = User::where('email', 'asesor@gmail.com')->first();

        if (!$user) {
            $this->command->error('❌ Usuario asesor@gmail.com no encontrado');
            return;
        }

        $this->command->info("✓ Usuario encontrado: {$user->name}");
        $this->command->info("  Email: {$user->email}");
        $this->command->info("  ID: {$user->id}");

        // Verificar rol
        if ($user->hasRole('Asesor')) {
            $this->command->info("✓ Usuario tiene rol 'Asesor'");
        } else {
            $this->command->error("❌ Usuario NO tiene rol 'Asesor'");
            $roles = $user->roles->pluck('name')->implode(', ');
            $this->command->info("  Roles actuales: {$roles}");
        }

        // Verificar registro de Asesor
        $asesor = Asesor::where('user_id', $user->id)->first();

        if (!$asesor) {
            $this->command->error("❌ Usuario no tiene registro en tabla 'asesors'");
            $this->command->error("   ¡ESTE ES EL PROBLEMA! CreateCliente requiere este registro.");
            return;
        }

        $this->command->info("✓ Registro Asesor encontrado (ID: {$asesor->id})");
        $this->command->info("  Estado: {$asesor->estado_asesor}");

        if ($asesor->persona) {
            $this->command->info("  Persona: {$asesor->persona->nombre} {$asesor->persona->apellidos}");
        } else {
            $this->command->warn("  ⚠ Asesor sin persona asociada");
        }

        // Verificar permisos
        $this->command->info("\n=== Verificación de Permisos ===");
        $permisos = ['view_any_cliente', 'create_cliente', 'update_cliente'];
        foreach ($permisos as $permiso) {
            if ($user->can($permiso)) {
                $this->command->info("  ✓ {$permiso}");
            } else {
                $this->command->error("  ❌ {$permiso}");
            }
        }

        // Verificar política
        $this->command->info("\n=== Verificación de Política ===");
        $policy = app(\App\Policies\ClientePolicy::class);

        if ($policy->create($user)) {
            $this->command->info("  ✓ ClientePolicy::create() permite creación");
        } else {
            $this->command->error("  ❌ ClientePolicy::create() BLOQUEA creación");
            $this->command->error("     ¡ESTE PODRÍA SER EL PROBLEMA!");
        }

        // Simular creación de cliente
        $this->command->info("\n=== Simulación de Creación ===");
        try {
            $this->command->info("Simulando mutateFormDataBeforeCreate()...");

            // Este es el código que se ejecuta en CreateCliente.php línea 23-34
            if ($user->hasRole('Asesor')) {
                $asesorTest = Asesor::where('user_id', $user->id)->first();

                if (!$asesorTest) {
                    $this->command->error("  ❌ FALLÓ: No se encontró registro de Asesor");
                    $this->command->error("     Mensaje de error que vería el usuario:");
                    $this->command->error("     'El usuario autenticado no tiene un asesor asociado.'");
                } else {
                    $this->command->info("  ✓ PASÓ: Se encontró registro de Asesor (ID: {$asesorTest->id})");
                    $this->command->info("     asesor_id sería asignado como: {$asesorTest->id}");
                }
            }

        } catch (\Exception $e) {
            $this->command->error("  ❌ Error durante simulación: " . $e->getMessage());
        }

        $this->command->info("\n=== Conclusión ===");
        $this->command->info("Si todos los checkmarks (✓) están presentes arriba,");
        $this->command->info("el problema podría estar en:");
        $this->command->info("  1. Caché del navegador");
        $this->command->info("  2. Sesión de usuario no actualizada");
        $this->command->info("  3. Configuración de Filament Shield");
        $this->command->info("  4. JavaScript bloqueando el botón en el frontend");
    }
}
