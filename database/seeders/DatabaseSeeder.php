<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Orquestador de seeders para el entorno de desarrollo.
 *
 * Orden de ejecución:
 *   1. PermissionSeeder        — roles y permisos base
 *   2. ProductoFinancieroSeeder  — productos financieros disponibles
 *   3. UsersSeeder              — usuarios del sistema con roles
 *   4. ClientesGruposSeeder     — grupos y clientes de prueba
 *   5. PrestamosDesarrolloSeeder — préstamos con cuotas individuales
 *
 * Para testing se usa migrate:fresh --seed con conexión de testing,
 * que ejecuta este mismo orquestador sobre una DB limpia.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            ProductoFinancieroSeeder::class,
            UsersSeeder::class,
            ClientesGruposSeeder::class,
            PrestamosDesarrolloSeeder::class,
        ]);
    }
}
