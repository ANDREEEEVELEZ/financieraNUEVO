<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class CheckAllAsesorUsersSeeder extends Seeder
{
    /**
     * Verificar TODOS los usuarios con rol Asesor.
     */
    public function run(): void
    {
        $this->command->info('=== VERIFICANDO TODOS LOS USUARIOS CON ROL ASESOR ===');

        $asesores = User::whereHas('roles', function ($q) {
            $q->where('name', 'Asesor');
        })->get();

        $this->command->info("Encontrados {$asesores->count()} usuarios con rol 'Asesor'\n");

        foreach ($asesores as $user) {
            $this->command->info("=== Usuario: {$user->name} ===");
            $this->command->line("  Email: {$user->email}");
            $this->command->line("  ID: {$user->id}");

            // Verificar permisos de cliente
            $canCreate = $user->can('create_cliente');
            $canViewAny = $user->can('view_any_cliente');

            if ($canCreate && $canViewAny) {
                $this->command->info("  ✓ Permisos de cliente CORRECTOS");
            } else {
                $this->command->error("  ❌ FALTAN permisos de cliente");
                $this->command->line("    create_cliente: " . ($canCreate ? 'SÍ' : 'NO'));
                $this->command->line("    view_any_cliente: " . ($canViewAny ? 'SÍ' : 'NO'));
            }

            // Verificar registro de Asesor
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                $this->command->info("  ✓ Tiene registro en tabla 'asesors' (ID: {$asesor->id})");
            } else {
                $this->command->error("  ❌ NO tiene registro en tabla 'asesors'");
            }

            $this->command->line("");
        }

        // Mostrar rol Asesor y sus permisos
        $roleAsesor = Role::where('name', 'Asesor')->first();
        if ($roleAsesor) {
            $permCount = $roleAsesor->permissions->count();
            $this->command->info("=== ROL 'Asesor' tiene {$permCount} permisos ===");
        }
    }
}
