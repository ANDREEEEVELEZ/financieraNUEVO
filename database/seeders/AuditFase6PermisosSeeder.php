<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Asesor;
use App\Models\Cliente;
use App\Policies\ClientePolicy;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Gate;

/**
 * AUDITORÍA FASE 6: Sistema de Permisos
 * 
 * Verifica cada paso del flujo de creación de cliente por Asesor:
 * 1. ClientePolicy::create() verifica $user->can('create_cliente')
 * 2. mutateFormDataBeforeCreate() asigna asesor_id automáticamente
 * 3. getEloquentQuery() filtra solo clientes del asesor
 */
class AuditFase6PermisosSeeder extends Seeder
{
    protected int $passed = 0;
    protected int $failed = 0;
    protected array $errors = [];
    protected array $warnings = [];

    public function run(): void
    {
        $this->command->info("\n" . str_repeat('=', 70));
        $this->command->info('   AUDITORÍA FASE 6: SISTEMA DE PERMISOS - FLUJO ASESOR');
        $this->command->info(str_repeat('=', 70));

        // Auditoría paso por paso según el diagrama
        $this->auditPaso1_BotonCrearCliente();
        $this->auditPaso2_ClientePolicyCreate();
        $this->auditPaso3_PermisoCreateCliente();
        $this->auditPaso5_MutateFormDataBeforeCreate();
        $this->auditPaso6_AsociacionAsesorId();
        $this->auditPaso7_GetEloquentQuery();
        $this->auditProblemasConocidos();

        $this->mostrarResumen();
    }

    protected function test(string $nombre, callable $prueba, string $categoria): bool
    {
        try {
            $resultado = $prueba();
            if ($resultado === true) {
                $this->command->info("  ✓ {$nombre}");
                $this->passed++;
                return true;
            } else {
                $this->command->error("  ✗ {$nombre}");
                if (is_string($resultado)) {
                    $this->command->error("    → {$resultado}");
                }
                $this->failed++;
                $this->errors[] = "{$categoria}: {$nombre}";
                return false;
            }
        } catch (\Exception $e) {
            $this->command->error("  ✗ {$nombre}");
            $this->command->error("    → Error: " . $e->getMessage());
            $this->failed++;
            $this->errors[] = "{$categoria}: {$nombre} - " . $e->getMessage();
            return false;
        }
    }

    protected function warn(string $message): void
    {
        $this->command->warn("  ⚠ {$message}");
        $this->warnings[] = $message;
    }

    protected function auditPaso1_BotonCrearCliente(): void
    {
        $this->command->info("\n--- PASO 1: Asesor hace clic en 'Crear Cliente' ---");

        // Verificar que ListClientes tiene el botón crear
        $listClientesPath = app_path('Filament/Dashboard/Resources/ClienteResource/Pages/ListClientes.php');

        $this->test('ListClientes.php existe', function () use ($listClientesPath) {
            return file_exists($listClientesPath);
        }, 'Paso1');

        if (file_exists($listClientesPath)) {
            $content = file_get_contents($listClientesPath);

            $this->test('ListClientes tiene CreateAction', function () use ($content) {
                return strpos($content, 'CreateAction::make()') !== false ||
                    strpos($content, 'Actions\CreateAction::make()') !== false;
            }, 'Paso1');
        }
    }

    protected function auditPaso2_ClientePolicyCreate(): void
    {
        $this->command->info("\n--- PASO 2: Filament verifica ClientePolicy::create() ---");

        $policyPath = app_path('Policies/ClientePolicy.php');

        $this->test('ClientePolicy.php existe', function () use ($policyPath) {
            return file_exists($policyPath);
        }, 'Paso2');

        if (file_exists($policyPath)) {
            $content = file_get_contents($policyPath);

            $this->test('ClientePolicy tiene método create()', function () use ($content) {
                return strpos($content, 'public function create(') !== false;
            }, 'Paso2');

            $this->test('create() verifica $user->can(\'create_cliente\')', function () use ($content) {
                return strpos($content, "can('create_cliente')") !== false;
            }, 'Paso2');
        }

        // Verificar que la política está siendo descubierta por Laravel
        $this->test('ClientePolicy está registrada en Gate', function () {
            $policy = Gate::getPolicyFor(\App\Models\Cliente::class);
            return $policy instanceof ClientePolicy;
        }, 'Paso2');
    }

    protected function auditPaso3_PermisoCreateCliente(): void
    {
        $this->command->info("\n--- PASO 3: Permiso 'create_cliente' concedido ---");

        // Verificar que el permiso existe
        $this->test('Permiso create_cliente existe en BD', function () {
            return Permission::where('name', 'create_cliente')->exists();
        }, 'Paso3');

        // Verificar que está asignado al rol Asesor
        $roleAsesor = Role::where('name', 'Asesor')->first();
        if ($roleAsesor) {
            $this->test('Rol Asesor tiene permiso create_cliente', function () use ($roleAsesor) {
                return $roleAsesor->hasPermissionTo('create_cliente');
            }, 'Paso3');
        } else {
            $this->warn('Rol Asesor no existe en la base de datos');
        }

        // Verificar usuarios Asesor específicos
        $usersAsesor = User::whereHas('roles', fn($q) => $q->where('name', 'Asesor'))->get();

        $this->command->info("  Verificando {$usersAsesor->count()} usuarios con rol Asesor:");

        foreach ($usersAsesor as $user) {
            $canCreate = $user->can('create_cliente');
            $status = $canCreate ? '✓' : '✗';
            $this->command->line("    {$status} {$user->email}: " . ($canCreate ? 'PUEDE crear' : 'NO puede crear'));

            if (!$canCreate) {
                $this->errors[] = "Usuario {$user->email} no puede crear clientes";
            }
        }
    }

    protected function auditPaso5_MutateFormDataBeforeCreate(): void
    {
        $this->command->info("\n--- PASO 5: CreateCliente::mutateFormDataBeforeCreate() ---");

        $createClientePath = app_path('Filament/Dashboard/Resources/ClienteResource/Pages/CreateCliente.php');

        $this->test('CreateCliente.php existe', function () use ($createClientePath) {
            return file_exists($createClientePath);
        }, 'Paso5');

        if (file_exists($createClientePath)) {
            $content = file_get_contents($createClientePath);

            $this->test('Tiene método mutateFormDataBeforeCreate()', function () use ($content) {
                return strpos($content, 'function mutateFormDataBeforeCreate') !== false;
            }, 'Paso5');

            $this->test('Verifica rol Asesor', function () use ($content) {
                return strpos($content, "hasRole('Asesor')") !== false;
            }, 'Paso5');

            $this->test('Busca registro en tabla asesors', function () use ($content) {
                return strpos($content, "Asesor::where('user_id'") !== false;
            }, 'Paso5');

            $this->test('Asigna asesor_id automáticamente', function () use ($content) {
                return strpos($content, "\$data['asesor_id'] = \$asesor->id") !== false;
            }, 'Paso5');
        }
    }

    protected function auditPaso6_AsociacionAsesorId(): void
    {
        $this->command->info("\n--- PASO 6: Cliente creado con asesor_id del Asesor ---");

        // Verificar que todos los usuarios Asesor tienen registro en tabla asesors
        $usersAsesor = User::whereHas('roles', fn($q) => $q->where('name', 'Asesor'))->get();

        $this->command->info("  Verificando asociación User → Asesor:");

        $sinAsociacion = [];

        foreach ($usersAsesor as $user) {
            $asesor = Asesor::where('user_id', $user->id)->first();

            if ($asesor) {
                $this->command->line("    ✓ {$user->email} → Asesor ID: {$asesor->id}");
            } else {
                $this->command->error("    ✗ {$user->email} → SIN REGISTRO EN TABLA 'asesors'");
                $sinAsociacion[] = $user->email;
            }
        }

        $this->test('Todos los usuarios Asesor tienen registro en tabla asesors', function () use ($sinAsociacion) {
            if (empty($sinAsociacion)) {
                return true;
            }
            return 'Usuarios sin asociación: ' . implode(', ', $sinAsociacion);
        }, 'Paso6');

        // Verificar clientes existentes
        $clientesSinAsesor = Cliente::whereNull('asesor_id')->count();
        if ($clientesSinAsesor > 0) {
            $this->warn("Hay {$clientesSinAsesor} clientes sin asesor_id asignado");
        } else {
            $this->command->info("  ✓ Todos los clientes tienen asesor_id asignado");
        }
    }

    protected function auditPaso7_GetEloquentQuery(): void
    {
        $this->command->info("\n--- PASO 7: getEloquentQuery() filtra solo SUS clientes ---");

        $resourcePath = app_path('Filament/Dashboard/Resources/ClienteResource.php');

        $this->test('ClienteResource.php existe', function () use ($resourcePath) {
            return file_exists($resourcePath);
        }, 'Paso7');

        if (file_exists($resourcePath)) {
            $content = file_get_contents($resourcePath);

            $this->test('Tiene método getEloquentQuery()', function () use ($content) {
                return strpos($content, 'function getEloquentQuery()') !== false;
            }, 'Paso7');

            $this->test('Filtra por asesor_id para rol Asesor', function () use ($content) {
                return strpos($content, "where('asesor_id', \$asesor->id)") !== false ||
                    strpos($content, "where('asesor_id'") !== false;
            }, 'Paso7');

            $this->test('Verifica rol Asesor antes de filtrar', function () use ($content) {
                return strpos($content, "hasRole('Asesor')") !== false;
            }, 'Paso7');
        }

        // Verificar que el filtro funciona correctamente simulando un asesor
        $asesorConClientes = Asesor::whereHas('clientes')->first();
        if ($asesorConClientes) {
            $totalClientes = Cliente::count();
            $clientesDelAsesor = Cliente::where('asesor_id', $asesorConClientes->id)->count();

            $this->command->info("  ℹ Asesor ID {$asesorConClientes->id} tiene {$clientesDelAsesor} de {$totalClientes} clientes");

            if ($clientesDelAsesor > 0 && $clientesDelAsesor < $totalClientes) {
                $this->command->info("  ✓ El filtro debería mostrar solo sus {$clientesDelAsesor} clientes");
            }
        }
    }

    protected function auditProblemasConocidos(): void
    {
        $this->command->info("\n--- PROBLEMAS POTENCIALES DETECTADOS ---");

        // Problema 1: Usuario sin registro en asesors
        $usersAsesorSinRegistro = User::whereHas('roles', fn($q) => $q->where('name', 'Asesor'))
            ->whereDoesntHave('asesor')
            ->get();

        if ($usersAsesorSinRegistro->count() > 0) {
            $this->command->error("  ❌ PROBLEMA CRÍTICO: {$usersAsesorSinRegistro->count()} usuario(s) Asesor SIN registro en tabla 'asesors'");
            foreach ($usersAsesorSinRegistro as $user) {
                $this->command->error("     → {$user->email} (ID: {$user->id})");
            }
            $this->command->info("     SOLUCIÓN: php artisan db:seed --class=FixAsesorUserAssociationSeeder");
            $this->errors[] = "Usuarios Asesor sin registro en tabla asesors";
        } else {
            $this->command->info("  ✓ Todos los usuarios Asesor tienen registro en tabla 'asesors'");
        }

        // Problema 2: Permisos faltantes
        $permisosNecesarios = ['create_cliente', 'view_any_cliente', 'view_cliente', 'update_cliente'];
        $permisosFaltantes = [];

        foreach ($permisosNecesarios as $permiso) {
            if (!Permission::where('name', $permiso)->exists()) {
                $permisosFaltantes[] = $permiso;
            }
        }

        if (!empty($permisosFaltantes)) {
            $this->command->error("  ❌ PROBLEMA: Permisos faltantes: " . implode(', ', $permisosFaltantes));
            $this->command->info("     SOLUCIÓN: php artisan shield:generate --all --panel=dashboard");
            $this->errors[] = "Permisos Shield faltantes";
        } else {
            $this->command->info("  ✓ Todos los permisos necesarios existen");
        }

        // Problema 3: Rol Asesor sin permisos asignados
        $roleAsesor = Role::where('name', 'Asesor')->first();
        if ($roleAsesor) {
            $permisosSinAsignar = [];
            foreach ($permisosNecesarios as $permiso) {
                if (!$roleAsesor->hasPermissionTo($permiso)) {
                    $permisosSinAsignar[] = $permiso;
                }
            }

            if (!empty($permisosSinAsignar)) {
                $this->command->error("  ❌ PROBLEMA: Rol Asesor no tiene: " . implode(', ', $permisosSinAsignar));
                $this->command->info("     SOLUCIÓN: php artisan db:seed --class=AssignShieldPermissionsToAsesorSeeder");
                $this->errors[] = "Permisos no asignados al rol Asesor";
            } else {
                $this->command->info("  ✓ Rol Asesor tiene todos los permisos necesarios");
            }
        }
    }

    protected function mostrarResumen(): void
    {
        $total = $this->passed + $this->failed;
        $porcentaje = $total > 0 ? round(($this->passed / $total) * 100, 1) : 0;

        $this->command->info("\n" . str_repeat('=', 70));
        $this->command->info('                    RESUMEN DE AUDITORÍA FASE 6');
        $this->command->info(str_repeat('=', 70));

        if ($this->failed === 0 && empty($this->warnings)) {
            $this->command->info("🎉 ¡ÉXITO! La implementación de Fase 6 está correcta");
        } elseif ($this->failed === 0) {
            $this->command->warn("⚠ Implementación correcta con advertencias");
        } else {
            $this->command->error("❌ Se encontraron problemas en la implementación");
        }

        $this->command->info("\n  Pruebas totales: {$total}");
        $this->command->info("  ✓ Pasadas: {$this->passed}");
        $this->command->error("  ✗ Fallidas: {$this->failed}");
        $this->command->info("  Porcentaje de éxito: {$porcentaje}%");

        if (!empty($this->errors)) {
            $this->command->info("\n--- ERRORES A CORREGIR ---");
            foreach ($this->errors as $error) {
                $this->command->error("  • {$error}");
            }
        }

        if (!empty($this->warnings)) {
            $this->command->info("\n--- ADVERTENCIAS ---");
            foreach ($this->warnings as $warning) {
                $this->command->warn("  • {$warning}");
            }
        }

        $this->command->info("\n" . str_repeat('=', 70));
    }
}
