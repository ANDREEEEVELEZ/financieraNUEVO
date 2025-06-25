<?php

namespace Tests\Feature;

use App\Models\Grupo;
use App\Models\Cliente;
use App\Models\Prestamo;
use App\Models\Persona;
use App\Models\Asesor;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GestionIntegrantesTest extends TestCase
{
    use RefreshDatabase;

    public function test_puede_remover_integrante_sin_prestamos()
    {
        // Crear un grupo sin préstamos
        $grupo = Grupo::factory()->create(['estado_grupo' => 'Activo']);
        $cliente = Cliente::factory()->create();
        
        // Agregar cliente al grupo
        $grupo->clientes()->attach($cliente->id, [
            'fecha_ingreso' => now(),
            'estado_grupo_cliente' => 'Activo'
        ]);
        
        // Verificar que el cliente está en el grupo
        $this->assertTrue($grupo->clientes()->where('cliente_id', $cliente->id)->exists());
        
        // Remover cliente
        $resultado = $grupo->removerCliente($cliente->id);
        
        // Verificar que se removió correctamente
        $this->assertTrue($resultado);
        $this->assertFalse($grupo->clientes()->where('cliente_id', $cliente->id)->exists());
        $this->assertTrue($grupo->exIntegrantes()->where('cliente_id', $cliente->id)->exists());
    }

    public function test_no_puede_remover_integrante_con_prestamos()
    {
        // Crear un grupo con préstamo activo
        $grupo = Grupo::factory()->create(['estado_grupo' => 'Activo']);
        $cliente = Cliente::factory()->create();
        
        // Agregar cliente al grupo
        $grupo->clientes()->attach($cliente->id, [
            'fecha_ingreso' => now(),
            'estado_grupo_cliente' => 'Activo'
        ]);
        
        // Crear préstamo activo
        Prestamo::factory()->create([
            'grupo_id' => $grupo->id,
            'estado' => 'Aprobado'
        ]);
        
        // Intentar remover cliente debe fallar
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No se puede remover integrantes de un grupo con préstamos activos.');
        
        $grupo->removerCliente($cliente->id);
    }

    public function test_puede_transferir_integrante_sin_prestamos()
    {
        // Crear dos grupos sin préstamos
        $grupoOrigen = Grupo::factory()->create(['estado_grupo' => 'Activo']);
        $grupoDestino = Grupo::factory()->create(['estado_grupo' => 'Activo']);
        $cliente = Cliente::factory()->create();
        
        // Agregar cliente al grupo origen
        $grupoOrigen->clientes()->attach($cliente->id, [
            'fecha_ingreso' => now(),
            'estado_grupo_cliente' => 'Activo'
        ]);
        
        // Transferir cliente
        $resultado = $grupoOrigen->transferirClienteAGrupo($cliente->id, $grupoDestino->id);
        
        // Verificar transferencia
        $this->assertTrue($resultado);
        $this->assertFalse($grupoOrigen->clientes()->where('cliente_id', $cliente->id)->exists());
        $this->assertTrue($grupoOrigen->exIntegrantes()->where('cliente_id', $cliente->id)->exists());
        $this->assertTrue($grupoDestino->clientes()->where('cliente_id', $cliente->id)->exists());
    }

    public function test_detecta_prestamos_activos_correctamente()
    {
        $grupo = Grupo::factory()->create(['estado_grupo' => 'Activo']);
        
        // Sin préstamos
        $this->assertFalse($grupo->tienePrestamosActivos());
        
        // Con préstamo pendiente
        Prestamo::factory()->create([
            'grupo_id' => $grupo->id,
            'estado' => 'Pendiente'
        ]);
        $this->assertTrue($grupo->tienePrestamosActivos());
        
        // Limpiar y probar con préstamo aprobado
        $grupo->prestamos()->delete();
        Prestamo::factory()->create([
            'grupo_id' => $grupo->id,
            'estado' => 'Aprobado'
        ]);
        $this->assertTrue($grupo->tienePrestamosActivos());
        
        // Con préstamo finalizado no debe bloquear
        $grupo->prestamos()->delete();
        Prestamo::factory()->create([
            'grupo_id' => $grupo->id,
            'estado' => 'Finalizado'
        ]);
        $this->assertFalse($grupo->tienePrestamosActivos());
    }
}
