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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\View;

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
                                ->rows(5)
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

     
        $this->query = '';
        $this->form->fill([
            'query' => '',
            'response' => $this->response,
            'activeTab' => 0]); // regresar a pestaña Asistente
    }

    public function clientesParaUsuario($user)
    {
        if ($user->hasAnyRole(['administrador', 'jefe de operaciones', 'efe de credito'])) {
            return Cliente::all();
        }

        if ($user->hasRole('asesor')) {
            return Cliente::where('asesor_id', $user->id)->get();
        }

        return collect();
    }
    protected function filtrarPorAsesor($modelClass, $user)
{
    if (!$user->hasRole('asesor')) return $modelClass::query();

    switch ($modelClass) {
        case Grupo::class:
            return $modelClass::where('asesor_id', $user->id);

        case Cliente::class:
            return $modelClass::where('asesor_id', $user->id);
        case GrupoCliente::class:
              return $modelClass::whereHas('grupo', fn($q) => $q->where('asesor_id', $user->id));

        case Prestamo::class:
            return $modelClass::whereHas('grupo', fn($q) => $q->where('asesor_id', $user->id));

        case PrestamoIndividual::class:
            return $modelClass::whereHas('cliente', fn($q) => $q->where('asesor_id', $user->id));

        case CuotasGrupales::class:
            return $modelClass::whereHas('prestamo.grupo', fn($q) => $q->where('asesor_id', $user->id));

        case pago::class:
            return $modelClass::whereHas('cuotaGrupal.prestamo.grupo', fn($q) => $q->where('asesor_id', $user->id));
        case DetallePago::class:
            return $modelClass::whereHas('prestamoIndividual.cliente', fn($q) => $q->where('asesor_id', $user->id));

        case mora::class:
            return $modelClass::whereHas('cuotaGrupal.prestamo.grupo', fn($q) => $q->where('asesor_id', $user->id));

        case Retanqueo::class:
            return $modelClass::where('asesor_id', $user->id);

        case RetanqueoIndividual::class:
            return $modelClass::whereHas('cliente', fn($q) => $q->where('asesor_id', $user->id));

        default:
            return $modelClass::query();
    }
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
        $queryLower = strtolower($query);

        // Respuestas rápidas para algunas consultas comunes
        if (str_contains($queryLower, 'cuántos clientes') || str_contains($queryLower, 'total de clientes')) {
            $clientes = $this->clientesParaUsuario($user);
            return "Tienes {$clientes->count()} clientes asignados.";
        }

        if (str_contains($queryLower, 'lista de clientes')) {
            $clientes = $this->clientesParaUsuario($user);
            if ($clientes->isEmpty()) {
                return "No tienes clientes asignados.";
            }
            $nombres = $clientes->pluck('nombre')->take(10)->join(', ');
            return "Tus clientes son: " . $nombres;
        }

        if (str_contains($queryLower, 'cuántos pagos') || str_contains($queryLower, 'total de pagos')) {
            $totalPagos = Pago::count();
            return "Hay un total de {$totalPagos} pagos registrados.";
        }

        if (str_contains($queryLower, 'total de cuotas')) {
            $totalCuotas = CuotasGrupales::sum('monto_cuota_grupal');
            return "El total de todas las cuotas registradas es S/ {$totalCuotas}.";
        }

        // Contexto para que OpenAI conozca datos actuales
        $clientes = $this->clientesParaUsuario($user);
        $clientesCount = $clientes->count();
        $nombresClientes = $clientes->pluck('nombre')->take(5)->join(', ');
        $totalPagos = Pago::sum('monto_pagado');
        $totalCuotas = CuotasGrupales::sum('monto_cuota_grupal');

        // Contexto detallado con base de datos y reglas para OpenAI
        $systemPrompt = <<<EOT
Eres un asistente virtual experto en gestión financiera para una microfinanciera.
Debes responder preguntas con base en la siguiente base de datos financiera, que tiene las siguientes tablas y relaciones clave:
- users (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, active)
- personas (id, DNI, nombre, apellidos, sexo, fecha_nacimiento, celular, correo, dirección, distrito, estado_civil)
- asesores (id, persona_id FK personas, user_id FK users, codigo_asesor, fecha_ingreso, estado_asesor)
- clientes (id, persona_id FK personas, asesor_id FK asesores, infocorp, ciclo, condicion_vivienda, actividad, condicion_personal, estado_cliente)
- grupos (id, nombre_grupo, numero_integrantes, fecha_registro, calificacion_grupo, estado_grupo, asesor_id FK asesores)
- grupo_cliente (id, grupo_id FK grupos, cliente_id FK clientes, fecha_ingreso, rol, estado_grupo_cliente)
- prestamos (id, grupo_id FK grupos, tasa_interes, monto_prestado_total, monto_devolver, cantidad_cuotas, fecha_prestamo, frecuencia, estado, calificacion)
- prestamo_individual (id, prestamo_id FK prestamos, cliente_id FK clientes, monto_prestado_individual, monto_cuota_prestamo_individual, monto_devolver_individual, seguro, interes, estado)
- cuotas_grupales (id, prestamo_id FK prestamos, numero_cuota, monto_cuota_grupal, fecha_vencimiento, saldo_pendiente, estado_cuota_grupal, estado_pago)
- moras (id, cuota_grupal_id FK cuotas_grupales, estado_mora, fecha_atraso)
- pagos (id, cuota_grupal_id FK cuotas_grupales, tipo_pago, monto_pagado, monto_mora_pagada, fecha_pago, estado_pago, observaciones)
- detalles_pago (id, pago_id FK pagos, prestamo_individual_id FK prestamo_individual, monto_pagado, estado_pago_individual)
- retanqueos (id, prestamo_id FK prestamos, grupo_id FK grupos, asesore_id FK asesores, monto_retanqueado, monto_devolver, monto_desembolsar, cantidad_cuotas_retanqueo, aceptado, fecha_aceptacion, estado_retanqueo)
- retanqueo_individual (id, retanqueo_id FK retanqueos, cliente_id FK clientes, monto_solicitado, monto_desembolsar, monto_cuota_retanqueo, estado_retanqueo_individual)
- Un asesor puede tener muchos grupos y muchos clientes.
- Un grupo tiene muchos clientes (relación grupo_cliente).
- Cada grupo puede tener un préstamo grupal, que se divide en préstamos individuales por cliente.
- Las cuotas grupales pertenecen a préstamos y generan pagos y moras.
- Cada pago tiene detalles por préstamo individual.
- Los retanqueos son nuevos préstamos sobre el préstamo grupal, también divididos en retanqueos individuales por cliente.



Reglas de seguridad y uso:

- Solo se permiten consultas SELECT para evitar modificaciones o riesgos de seguridad.
- La API debe responder con información real y precisa sobre clientes, pagos, cuotas, préstamos y moras.
- Debe entender relaciones entre clientes, sus asesores, grupos y sus préstamos.
- Las respuestas deben ser breves, claras y operativas, adecuadas al perfil del usuario consultante.
-Importante: la tabla users NO tiene columna 'username'. Para filtrar usuarios por nombre, usa la columna 'name'. Para identificar usuarios únicos, usa la columna 'id'. No generes consultas con 'username'.
-Cuando hagas consultas SQL para identificar usuarios, solo usa 'id' o 'name' en la tabla users, nunca 'username'.
Contexto actual para el usuario: 
- Clientes asignados: {$clientesCount}
- Nombres de algunos clientes: {$nombresClientes}
- Total de pagos realizados: S/ {$totalPagos}
- Total de cuotas registradas: S/ {$totalCuotas}

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
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0,
            'max_tokens' => 50,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? 'No se obtuvo respuesta.';
        }

        Log::error('Error llamada OpenAI: ' . $response->body());
        return 'Error al comunicarse con el asistente virtual.';
    }
}
