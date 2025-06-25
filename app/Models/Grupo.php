<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Grupo extends Model
{
    use HasFactory;

    protected $table = 'grupos'; // Especificando el nombre de la tabla si no sigue las convenciones de Laravel
    
    protected $fillable = [
        'nombre_grupo',
        'numero_integrantes',
        'fecha_registro',
        'calificacion_grupo',
        'estado_grupo',
        'asesor_id', // Relación con Asesor
    ];

    protected $casts = [
        'fecha_registro' => 'date',
    ];

    public function integrantes()
    {
       //  return $this->hasMany(Integrante::class); // Suposición de una relación con una tabla de integrantes
    }

    public function clientes()
    {
        return $this->belongsToMany(Cliente::class, 'grupo_cliente', 'grupo_id', 'cliente_id')
            ->withTimestamps()
            ->withPivot('fecha_ingreso', 'fecha_salida', 'rol', 'estado_grupo_cliente')
            ->whereNull('grupo_cliente.fecha_salida'); // Solo clientes activos
    }

    /**
     * Relación para obtener ex-integrantes (clientes que salieron del grupo)
     */
    public function exIntegrantes()
    {
        return $this->belongsToMany(Cliente::class, 'grupo_cliente', 'grupo_id', 'cliente_id')
            ->withTimestamps()
            ->withPivot('fecha_ingreso', 'fecha_salida', 'rol', 'estado_grupo_cliente')
            ->whereNotNull('grupo_cliente.fecha_salida'); // Solo ex-integrantes
    }

    /**
     * Relación para obtener todos los integrantes (activos e inactivos)
     */
    public function todosLosIntegrantes()
    {
        return $this->belongsToMany(Cliente::class, 'grupo_cliente', 'grupo_id', 'cliente_id')
            ->withTimestamps()
            ->withPivot('fecha_ingreso', 'fecha_salida', 'rol', 'estado_grupo_cliente');
    }

    /**
     * Verifica si el grupo tiene préstamos activos o pendientes
     */
    public function tienePrestamosActivos(): bool
    {
        return $this->prestamos()
            ->whereIn('estado', ['Pendiente', 'Aprobado'])
            ->exists();
    }

    /**
     * Obtiene los nombres de ex-integrantes para mostrar
     */
    public function getExIntegrantesNombresAttribute()
    {
        return $this->exIntegrantes()->with('persona')->get()->map(function($cliente) {
            $fechaSalida = $cliente->pivot->fecha_salida ? 
                ' (Salió: ' . \Carbon\Carbon::parse($cliente->pivot->fecha_salida)->format('d/m/Y') . ')' : '';
            return $cliente->persona->nombre . ' ' . $cliente->persona->apellidos . $fechaSalida;
        })->implode(', ');
    }

    /**
     * Remueve un cliente del grupo estableciendo fecha de salida
     */
    public function removerCliente($clienteId, $fechaSalida = null)
    {
        if ($this->tienePrestamosActivos()) {
            throw new \Exception('No se puede remover integrantes de un grupo con préstamos activos.');
        }

        // Verificar que el cliente existe en el grupo (activo)
        $cliente = $this->clientes()->where('clientes.id', $clienteId)->first();
        if (!$cliente) {
            throw new \Exception('El cliente no pertenece a este grupo o ya fue removido.');
        }

        // Verificar si el cliente es el líder grupal
        if ($cliente->pivot->rol === 'Líder Grupal') {
            $integrantesActivos = $this->clientes()->where('clientes.id', '!=', $clienteId)->count();
            if ($integrantesActivos > 0) {
                throw new \Exception('No se puede remover al líder grupal. Primero debe asignar un nuevo líder.');
            }
        }

        // Actualizar la tabla pivot con fecha de salida
        $this->todosLosIntegrantes()->updateExistingPivot($clienteId, [
            'fecha_salida' => $fechaSalida ?? now()->format('Y-m-d'),
            'estado_grupo_cliente' => 'Inactivo',
            'updated_at' => now()
        ]);

        // Verificar si el grupo se queda sin integrantes activos
        $integrantesActivosRestantes = $this->clientes()->count();
        if ($integrantesActivosRestantes === 0) {
            $this->estado_grupo = 'Inactivo';
        }

        // Actualizar el contador de integrantes
        $this->numero_integrantes = $integrantesActivosRestantes;
        $this->save();

        return true;
    }

    /**
     * Transfiere un cliente a otro grupo
     */
    public function transferirClienteAGrupo($clienteId, $nuevoGrupoId, $fechaSalida = null)
    {
        if ($this->tienePrestamosActivos()) {
            throw new \Exception('No se puede transferir integrantes de un grupo con préstamos activos.');
        }

        $nuevoGrupo = self::find($nuevoGrupoId);
        if (!$nuevoGrupo) {
            throw new \Exception('El grupo destino no existe.');
        }

        if ($nuevoGrupo->tienePrestamosActivos()) {
            throw new \Exception('No se puede agregar integrantes a un grupo con préstamos activos.');
        }

        // Verificar que el cliente existe en el grupo actual (ACTIVO)
        $cliente = $this->clientes()->where('clientes.id', $clienteId)->first();
        if (!$cliente) {
            throw new \Exception('El cliente no pertenece a este grupo o ya fue removido.');
        }

        // Verificar que el cliente no esté ya ACTIVO en el grupo destino
        $yaEstaActivoEnDestino = $nuevoGrupo->clientes()->where('clientes.id', $clienteId)->exists();
        if ($yaEstaActivoEnDestino) {
            throw new \Exception('El cliente ya pertenece activamente al grupo destino.');
        }

        // Verificar si el cliente es el líder grupal
        $esLider = ($cliente->pivot->rol === 'Líder Grupal');
        if ($esLider) {
            $integrantesActivos = $this->clientes()->where('clientes.id', '!=', $clienteId)->count();
            if ($integrantesActivos > 0) {
                throw new \Exception('No se puede transferir al líder grupal sin antes asignar un nuevo líder.');
            }
        }

        $fechaTransferencia = $fechaSalida ?? now()->format('Y-m-d');

        // Actualizar el cliente en el grupo actual (marcar como ex-integrante)
        $this->todosLosIntegrantes()->updateExistingPivot($clienteId, [
            'fecha_salida' => $fechaTransferencia,
            'estado_grupo_cliente' => 'Inactivo',
            'updated_at' => now()
        ]);

        // Verificar si el cliente ya estuvo en el grupo destino antes (ex-integrante)
        $existeEnDestino = $nuevoGrupo->todosLosIntegrantes()->where('clientes.id', $clienteId)->exists();
        
        if ($existeEnDestino) {
            // El cliente ya estuvo en este grupo antes, actualizar su registro a activo
            $nuevoGrupo->todosLosIntegrantes()->updateExistingPivot($clienteId, [
                'fecha_ingreso' => $fechaTransferencia,
                'fecha_salida' => null,
                'estado_grupo_cliente' => 'Activo',
                'rol' => 'Miembro',
                'updated_at' => now()
            ]);
        } else {
            // Es la primera vez que el cliente entra a este grupo
            $nuevoGrupo->todosLosIntegrantes()->attach($clienteId, [
                'fecha_ingreso' => $fechaTransferencia,
                'fecha_salida' => null,
                'estado_grupo_cliente' => 'Activo',
                'rol' => 'Miembro',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Verificar si el grupo origen se queda sin integrantes activos
        $integrantesActivosOrigen = $this->clientes()->count();
        if ($integrantesActivosOrigen === 0) {
            $this->estado_grupo = 'Inactivo';
        }

        // Actualizar contadores
        $this->numero_integrantes = $integrantesActivosOrigen;
        $this->save();

        $nuevoGrupo->numero_integrantes = $nuevoGrupo->clientes()->count();
        $nuevoGrupo->save();

        // Actualizar el asesor del cliente al del nuevo grupo
        $clienteModel = \App\Models\Cliente::find($clienteId);
        if ($clienteModel) {
            $clienteModel->asesor_id = $nuevoGrupo->asesor_id;
            $clienteModel->save();
        }

        return true;
    }

    public function prestamos()
    {
        return $this->hasMany(\App\Models\Prestamo::class, 'grupo_id');
    }

    public function getIntegrantesNombresAttribute()
    {
        return $this->clientes()->with('persona')->get()->map(function($cliente) {
            return $cliente->persona->nombre . ' ' . $cliente->persona->apellidos;
        })->implode(', ');
    }
    
    public function getNumeroIntegrantesRealAttribute()
    {
        return $this->clientes()->count();
    }
    /**
     * Sincroniza el estado del grupo según el estado del préstamo principal.
     */
    public function sincronizarEstadoPorPrestamoPrincipal()
    {
        // Considera solo el préstamo principal (más reciente o con estado relevante)
        $prestamo = $this->prestamos()->orderByDesc('id')->first();
        if ($prestamo) {
            $this->estado_grupo = $prestamo->estado;
            $this->save();
        }
    }

    public function asesor()
    {
        return $this->belongsTo(Asesor::class);
    }

    public function scopeVisiblePorUsuario($query, $user)
    {
        if ($user->hasRole('Asesor')) {
            $asesor = \App\Models\Asesor::where('user_id', $user->id)->first();
            if ($asesor) {
                return $query->where('asesor_id', $asesor->id);
            }
        } elseif ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return $query; // Mostrar todos los grupos
        }

        return $query->whereRaw('1 = 0'); // No mostrar nada si no aplica
    }
}