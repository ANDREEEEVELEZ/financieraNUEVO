<?php
namespace App\Filament\Dashboard\Pages;
use App\Models\ConsultaAsistente;
use Filament\Pages\Page;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Grupo;
use App\Models\Prestamo;    
use App\Models\PrestamoIndividual;
use App\Models\DetallePago;
use App\Models\Mora;
use App\Models\Retanqueo;
use App\Models\RetanqueoIndividual;
use App\Models\CuotasGrupales;
use App\Models\GrupoCliente;
use App\Models\Asesor; // Asegúrate de importar el modelo Asesor
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\View;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Http\Request;

class AsistenteVirtual extends Page
{
    use InteractsWithForms;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static string $view = 'filament.dashboard.pages.asistente-virtual';
    protected static ?string $navigationLabel = 'Asistente Virtual';
    public string $query = '';
    public string $response = '';
    public Collection $consultas;
    public int $activeTab = 0;

    public function mount(): void
    {
        $this->loadConsultas();
        $this->form->fill([
            'query' => '',
            'response' => '',
            'activeTab' => $this->activeTab,
        ]);
    }

    // Método para obtener el asesor_id del usuario actual
    protected function getAsesorId($user): ?int
    {
        if ($user->hasRole('asesor')) {
            $asesor = Asesor::where('user_id', $user->id)->first();
            return $asesor ? $asesor->id : null;
        }
        return null;
    }

    protected function loadConsultas(): void
    {
        $user = Auth::user();
        $rolesSupervisores = ['super_admin', 'administrador'];
        $rolesConAccesoCondicional = ['jefe de operaciones', 'jefe de crédito'];
        
        if ($user->hasAnyRole($rolesSupervisores)) {
            // Acceso total
            $this->consultas = ConsultaAsistente::latest()->take(10)->get();
        } elseif ($user->hasAnyRole($rolesConAccesoCondicional)) {
            // Acceso condicional: si no tiene registros, accede a todo
            if ($this->tieneRegistrosAsignados($user)) {
                // Tiene registros, solo ve sus consultas
                $this->consultas = ConsultaAsistente::where('user_id', $user->id)
                    ->latest()
                    ->take(10)
                    ->get();
            } else {
                // No tiene registros, puede ver todo
                $this->consultas = ConsultaAsistente::latest()->take(10)->get();
            }
        } else {
            // Asesores y otros roles, solo ven sus consultas
            $this->consultas = ConsultaAsistente::where('user_id', $user->id)
                ->latest()
                ->take(10)
                ->get();
        }
    }

    public function clientesParaUsuario($user)
    {
        if ($user->hasAnyRole(['super_admin', 'jefe de operaciones', 'Jefe de creditos'])) {
            return Cliente::all();
        }
        if ($user->hasRole('asesor')) {
            $asesorId = $this->getAsesorId($user);
            if ($asesorId) {
                return Cliente::where('asesor_id', $asesorId)->get();
            }
        }
        return collect();
    }

    public function gruposParaUsuario($user)
    {
        if ($user->hasAnyRole(['super_admin', 'jefe de operaciones', 'jefe de creditos'])) {
            return Grupo::all();
        }
        if ($user->hasRole('asesor')) {
            $asesorId = $this->getAsesorId($user);
            if ($asesorId) {
                return Grupo::where('asesor_id', $asesorId)->get();
            }
        }
        return collect();
    }

    public function prestamosParaUsuario($user)
    {
        if ($user->hasAnyRole(['super_admin', 'jefe de operaciones', 'jefe de creditos'])) {
            return Prestamo::all();
        }
        if ($user->hasRole('asesor')) {
            $asesorId = $this->getAsesorId($user);
            if ($asesorId) {
                return Prestamo::whereHas('grupo', function ($query) use ($asesorId) {
                    $query->where('asesor_id', $asesorId);
                })->get();
            }
        }
        return collect();
    }

    public function PagosParaUsuario($user)
    {
        if ($user->hasAnyRole(['super_admin', 'jefe de operaciones', 'jefe de creditos'])) {
            return Pago::all();
        }
        if ($user->hasRole('asesor')) {
            $asesorId = $this->getAsesorId($user);
            if ($asesorId) {
                return Pago::whereHas('cuotaGrupal.prestamo.grupo', function ($query) use ($asesorId) {
                    $query->where('asesor_id', $asesorId);
                })->get();
            }
        }
        return collect();
    }

    public function cuotasParaUsuario($user)
    {
        if ($user->hasAnyRole(['super_admin', 'jefe de operaciones', 'jefe de creditos'])) {
            return CuotasGrupales::all();
        }
        if ($user->hasRole('asesor')) {
            $asesorId = $this->getAsesorId($user);
            if ($asesorId) {
                return CuotasGrupales::whereHas('prestamo.grupo', function ($query) use ($asesorId) {
                    $query->where('asesor_id', $asesorId);
                })->get();
            }
        }
        return collect();
    }

    public function morasParaUsuario($user)
    {
        if ($user->hasAnyRole(['super_admin', 'jefe de operaciones', 'jefe de creditos'])) {
            return Mora::all();
        }
        if ($user->hasRole('asesor')) {
            $asesorId = $this->getAsesorId($user);
            if ($asesorId) {
                return Mora::whereHas('cuotaGrupal.prestamo.grupo', function ($query) use ($asesorId) {
                    $query->where('asesor_id', $asesorId);
                })->get();
            }
        }
        return collect();
    }

    protected function tieneRegistrosAsignados($user): bool
    {
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'jefe de credito'])) {
            return true; // Acceso total, siempre tiene registros
        }
        if ($user->hasRole('asesor')) {
            $asesorId = $this->getAsesorId($user);
            if ($asesorId) {
                return Cliente::where('asesor_id', $asesorId)->exists() ||
                       Grupo::where('asesor_id', $asesorId)->exists() ||
                       Prestamo::whereHas('grupo', fn($q) => $q->where('asesor_id', $asesorId))->exists();
            }
        }
        return false; // Otros roles no tienen registros asignados
    }

    protected function filtrarPorAsesor($modelClass, $user)
    {
        if (!$user->hasRole('asesor')) return $modelClass::query();
        
        $asesorId = $this->getAsesorId($user);
        if (!$asesorId) return $modelClass::query()->whereRaw('1 = 0'); // No devolver nada si no hay asesor_id
        
        switch ($modelClass) {
            case Grupo::class:
                return $modelClass::where('asesor_id', $asesorId);
            case Cliente::class:
                return $modelClass::where('asesor_id', $asesorId);
            case GrupoCliente::class:
                return $modelClass::whereHas('grupo', fn($q) => $q->where('asesor_id', $asesorId));
            case Prestamo::class:
                return $modelClass::whereHas('grupo', fn($q) => $q->where('asesor_id', $asesorId));
            case PrestamoIndividual::class:
                return $modelClass::whereHas('cliente', fn($q) => $q->where('asesor_id', $asesorId));
            case CuotasGrupales::class:
                return $modelClass::whereHas('prestamo.grupo', fn($q) => $q->where('asesor_id', $asesorId));
            case Pago::class: // Corregido: era 'pago' en minúscula
                return $modelClass::whereHas('cuotaGrupal.prestamo.grupo', fn($q) => $q->where('asesor_id', $asesorId));
            case DetallePago::class:
                return $modelClass::whereHas('prestamoIndividual.cliente', fn($q) => $q->where('asesor_id', $asesorId));
            case Mora::class: // Corregido: era 'mora' en minúscula
                return $modelClass::whereHas('cuotaGrupal.prestamo.grupo', fn($q) => $q->where('asesor_id', $asesorId));
            case Retanqueo::class:
                return $modelClass::where('asesor_id', $asesorId);
            case RetanqueoIndividual::class:
                return $modelClass::whereHas('cliente', fn($q) => $q->where('asesor_id', $asesorId));
            default:
                return $modelClass::query();
        }
    }

    // ... resto del código permanece igual ...

    protected function getFormSchema(): array
    {
        return [
            Hidden::make('activeTab')
                ->default(0)
                ->reactive()
                ->afterStateUpdated(fn ($state) => $this->activeTab = (int) $state),
            Tabs::make('AsistenteTabs')
                ->tabs([
                    Tabs\Tab::make('Asistente')
                        ->schema([
                            Textarea::make('query')
                                ->label('Consulta')
                                ->required()
                                ->placeholder('Escribe tu pregunta aquí...')
                                ->rows(3)
                                ->columnSpan(2)
                                ->reactive()
                                ->afterStateUpdated(fn ($state) => $this->query = $state),
                            Textarea::make('response')
                                ->label('Respuesta')
                                ->disabled()
                                ->placeholder('Aquí aparecerá la respuesta...')
                                ->rows(15)
                                ->columnSpan(2)
                                ->reactive()
                                ->afterStateUpdated(fn ($state) => $this->response = $state),
                            Actions::make([
                                Action::make('submitConsulta')
                                    ->label('Enviar Consulta')
                                    ->action('submitQuery')
                                    ->color('primary'),
                            ])->columnSpan(2)->alignment('right'),
                        ]),
                    Tabs\Tab::make('Historial')
                        ->schema([
                            View::make('filament.dashboard.pages.historial-table')
                                ->viewData(['consultas' => $this->consultas]),
                        ]),
                ])
                ->activeTab($this->activeTab + 1)
                ->reactive()
                ->afterStateUpdated(function ($state) {
                    $this->activeTab = $state - 1;
                    $this->form->fill(['activeTab' => $this->activeTab]);
                }),
        ];
    }

    public function submitQuery()
    {
        if (empty(trim($this->query))) {
            $this->notify('error', 'La consulta no puede estar vacía.');
            return;
        }
        $this->response = $this->processQuery($this->query);
        ConsultaAsistente::create([
            'user_id' => Auth::id(),
            'consulta' => $this->query,
            'respuesta' => $this->response,
        ]);
        $this->loadConsultas();
     
       // $this->query = '';
        $this->form->fill([
            //'query' => '',
            'response' => $this->response,
            'activeTab' => 0]); // regresar a pestaña Asistente
    }

    protected function isSafeSql(string $sql): bool
    {
        $sqlUpper = strtoupper($sql);
        $forbidden = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE', 'EXEC', 'MERGE', 'CALL'];
        foreach ($forbidden as $keyword) {
            if (str_contains($sqlUpper, $keyword)) {
                Log::warning("Consulta SQL rechazada por contener palabra prohibida: {$keyword}");
                return false;
            }
        }
        return str_starts_with($sqlUpper, 'SELECT');
    }

    protected function processQuery(string $query): string
    {
        $user = Auth::user();
        $tipo = 'general';
        
        if (str_contains(strtolower($query), 'cliente')) {
            $tipo = 'cliente';
        } elseif (str_contains(strtolower($query), 'mora')) {
            $tipo = 'mora';
        } elseif (str_contains(strtolower($query), 'grupo')) {
            $tipo = 'grupo';
        } elseif (str_contains(strtolower($query), 'pago')) {
            $tipo = 'pago';
        } elseif (str_contains(strtolower($query), 'cuota')) {
            $tipo = 'cuota';
        } elseif (str_contains(strtolower($query), 'préstamo') || str_contains(strtolower($query), 'prestamo')) {
            $tipo = 'prestamo';
        }

        $comentario = match($tipo) {
            'cliente' => 'Concéntrate en los datos del cliente, como nombre, asesor, y grupo.',
            'mora' => 'Busca detalles sobre moras activas, cuotas atrasadas o pagos incompletos.',
            'grupo' => 'Describe datos agregados por grupo como total de pagos, cuotas o moras.',
            'pago' => 'Enfócate en los pagos realizados, montos y fechas.',
            'cuota' => 'Proporciona información sobre cuotas, montos y estados de pago.',
            'prestamo' => 'Incluye detalles sobre préstamos, montos, tasas de interés y estados.',
            default => 'Responde la consulta de forma general, usando los datos más relevantes.'
        };

        $clientes = $this->clientesParaUsuario($user);
        $clientesCount = $clientes->count();
        $nombresClientes = $clientes->pluck('nombre')->take(5)->join(', ');

        $grupos = $this->gruposParaUsuario($user);
        $gruposCount = $grupos->count();
        $nombresGrupos = $grupos->pluck('nombre_grupo')->take(5)->join(', ');

        $prestamos = $this->prestamosParaUsuario($user);
        $totalPrestamos = $prestamos->count();

        $pagos = $this->PagosParaUsuario($user);
        $pagosCount = $pagos->count();
        $totalPagos = $pagos->sum('monto_pagado');

        $cuotas = $this->cuotasParaUsuario($user);
        $totalCuotas = $cuotas->sum('monto_cuota_grupal');

        $moras = $this->morasParaUsuario($user);
        $totalMoras = $moras->where('estado_mora', 'activo')->count();

        // Construir el systemPrompt incluyendo el esquema leído desde storage
        $esquema = <<<EOT
Tablas y columnas relevantes:

- asesores (id, persona_id, user_id, codigo_asesor, fecha_ingreso, estado_asesor, created_at, updated_at)
- clientes (id, persona_id, infocorp, ciclo, condicion_vivienda, actividad, condicion_personal, estado_cliente, created_at, updated_at, asesor_id)
- cuotas_grupales (id, prestamo_id, numero_cuota, monto_cuota_grupal, fecha_vencimiento, saldo_pendiente, estado_cuota_grupal, estado_pago, created_at, updated_at)
- grupo_cliente (id, grupo_id, cliente_id, fecha_ingreso, rol, estado_grupo_cliente, created_at, updated_at)
- grupos (id, nombre_grupo, numero_integrantes, fecha_registro, calificacion_grupo, estado_grupo, created_at, updated_at, asesor_id)
- moras (id, cuota_grupal_id, estado_mora, created_at, updated_at, fecha_atraso)
- pagos (id, cuota_grupal_id, tipo_pago, monto_pagado, monto_mora_pagada, fecha_pago, estado_pago, observaciones, created_at, updated_at)
- personas (id, DNI, nombre, apellidos, sexo, fecha_nacimiento, celular, correo, direccion, distrito, estado_civil, created_at, updated_at)
- prestamos (id, grupo_id, tasa_interes, monto_prestado_total, monto_devolver, cantidad_cuotas, fecha_prestamo, frecuencia, estado, calificacion, created_at, updated_at)
- prestamo_individual (id, prestamo_id, cliente_id, monto_prestado_individual, monto_cuota_prestamo_individual, monto_devolver_individual, seguro, interes, estado, created_at, updated_at)
- users (id, name, email, active)

(Otras tablas existen, pero estas son las principales para consultas financieras y clientes.)
EOT;

        $relaciones = <<<EOT
Relaciones clave:

- Un grupo (`grupos.id`) tiene muchos préstamos (`prestamos.grupo_id`).
- Un préstamo (`prestamos.id`) tiene muchas cuotas grupales (`cuotas_grupales.prestamo_id`).
- Una cuota grupal (`cuotas_grupales.id`) puede tener muchos pagos (`pagos.cuota_grupal_id`) y puede tener una mora (`moras.cuota_grupal_id`).
- Un cliente (`clientes.id`) está en muchos grupos a través de `grupo_cliente` (`grupo_cliente.cliente_id` y `grupo_cliente.grupo_id`).
- Un cliente tiene préstamos individuales (`prestamo_individual.cliente_id`) asociados a un préstamo grupal (`prestamo_individual.prestamo_id`).
- Un asesor (`asesores.id`) tiene grupos a su cargo (`grupos.asesor_id`) y clientes (`clientes.asesor_id`).
- La tabla `users` se relaciona con `asesores` mediante `asesores.user_id = users.id`.
EOT;

        $ejemplosSQL = <<<EOT
Ejemplos de consultas correctas:

1. ¿Cuánto se ha pagado en total por grupo?
<SQL>
SELECT g.nombre_grupo, SUM(p.monto_pagado) AS total_pagado
FROM grupos g
JOIN prestamos pr ON pr.grupo_id = g.id
JOIN cuotas_grupales cg ON cg.prestamo_id = pr.id
JOIN pagos p ON p.cuota_grupal_id = cg.id
GROUP BY g.nombre_grupo;
</SQL>

2. ¿Cuántas moras activas hay por grupo?
<SQL>
SELECT g.nombre_grupo, COUNT(m.id) AS moras_activas
FROM grupos g
JOIN prestamos pr ON pr.grupo_id = g.id
JOIN cuotas_grupales cg ON cg.prestamo_id = pr.id
JOIN moras m ON m.cuota_grupal_id = cg.id
WHERE m.estado_mora = 'activo'
GROUP BY g.nombre_grupo;
</SQL>
EOT;
   
        $systemPrompt = <<<EOT
Eres un asistente virtual experto en gestión financiera para una microfinanciera.
Debes responder preguntas con base en los datos reales del sistema y limitarte solo a operaciones de lectura (consultas SQL seguras).
A continuación se proporciona el esquema principal de la base de datos, las relaciones clave entre las tablas y algunos ejemplos útiles de consultas:
{$esquema} {$relaciones} 
{$ejemplosSQL}
{$comentario}
Tu tarea es ayudar a los usuarios a obtener información sobre clientes, pagos, cuotas, préstamos y moras, sin modificar la base de datos.

Reglas de seguridad y uso:
- Solo se permiten consultas SELECT para evitar modificaciones o riesgos de seguridad.
- La API debe responder con información real y precisa sobre clientes, pagos, cuotas, préstamos y moras.
- Debe entender relaciones entre clientes, sus asesores, grupos y sus préstamos.
- Las respuestas deben ser breves, claras y operativas, adecuadas al perfil del usuario consultante.
- Importante: la tabla users NO tiene columna 'username'. Para filtrar usuarios por nombre, usa la columna 'name'. Para identificar usuarios únicos, usa la columna 'id'. No generes consultas con 'username'.
- Cuando hagas consultas SQL para identificar usuarios, solo usa 'id' o 'name' en la tabla users, nunca 'username'.
- IMPORTANTE: Para asesores, usa la tabla 'asesores' que se relaciona con 'users' mediante 'asesores.user_id = users.id'. El asesor_id en otras tablas se refiere a 'asesores.id', no a 'users.id'.

Contexto actual para el usuario: 
- clientes tienen asesor_id (que apunta a asesores.id)
- grupos tienen asesor_id (que apunta a asesores.id)
- prestamos pertenecen a grupos
- cuotas_grupales están vinculadas a prestamos
- pagos están vinculados a cuotas_grupales
- moras están vinculadas a cuotas_grupales
- Clientes asignados: {$clientesCount}
- Nombres de algunos clientes: {$nombresClientes}
- Total de pagos realizados: S/ {$totalPagos}
- Total de cuotas registradas: S/ {$totalCuotas}
- Total de grupos: {$gruposCount}
- Nombres de algunos grupos: {$nombresGrupos}
- Total de préstamos: {$totalPrestamos}
- Total de moras activas: {$totalMoras}
- Total de pagos registrados: {$pagosCount}
- Recuerda que los asesores pueden ver solo los clientes y grupos asignados a ellos, mientras que supervisores tienen acceso total.

Cuando generes una consulta SQL, por favor encierra la consulta entre las etiquetas <SQL> y </SQL>.
No incluyas consultas que modifiquen la base de datos.
Responde con la consulta SQL y una breve explicación del resultado.
EOT;

        $userPrompt = <<<EOT
Usuario: "{$user->name}" ("{$user->getRoleNames()->implode(', ')}")
Pregunta: "{$query}"
EOT;

        $response = $this->callOpenAI($systemPrompt, $userPrompt);
        Log::info("Respuesta OpenAI para usuario {$user->id}: {$response}");
        
        // Extraer SQL entre etiquetas <SQL>...</SQL>
        if (preg_match('/<SQL>(.*?)<\/SQL>/is', $response, $matches)) {
            $sql = trim($matches[1]);
            if (!$this->isSafeSql($sql)) {
                return 'Consulta rechazada por motivos de seguridad.';
            }
            try {
                $result = DB::select($sql);
                if (empty($result)) {
                    return 'Consulta ejecutada correctamente. No se encontraron resultados.';
                }
                // Suponemos que el resultado es un array de stdClass con un solo campo
                $firstRow = $result[0];
                $fields = array_keys((array)$firstRow);
                if (count($result) === 1 && count($fields) === 1) {
                    $fieldName = $fields[0];
                    $value = $firstRow->$fieldName;
                    // Personaliza mensajes según el campo devuelto (ejemplo para total_grupos)
                    switch ($fieldName) {
                        case 'total_grupos':
                            return "Tienes {$value} grupos";
                        case 'total_clientes':
                            return "Tienes {$value} clientes";
                        case 'total_pagos':
                            return "Hay un total de {$value} pagos registrados";
                        // Agrega más casos según campos que uses
                        default:
                            return "{$fieldName}: {$value}";
                    }
                }
                // Si es un resultado más complejo, deja la respuesta en JSON formateado
                $jsonResult = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                return "Resultado de la consulta:\n{$jsonResult}";
            } catch (\Exception $e) {
                Log::error("Error al ejecutar consulta SQL: {$e->getMessage()}");
                return 'Error al ejecutar la consulta SQL: ' . $e->getMessage();
            }
        }
        return $response;
    }

    protected function callOpenAI(string $systemPrompt, string $userPrompt): string
    {
        $apiKey = config('services.openai.key');
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0,
            'max_tokens' => 1500,
        ]);
        if ($response->successful()) {
            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? 'No se obtuvo respuesta.';
        }
        Log::error('Error llamada OpenAI: ' . $response->body());
        return 'Error al comunicarse con el asistente virtual.';
    }
}