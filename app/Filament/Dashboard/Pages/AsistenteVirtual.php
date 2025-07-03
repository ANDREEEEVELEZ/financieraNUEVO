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
use App\Models\Asesor;
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
    protected function getAsesorId($user): ?int
    {
        if ($user->hasRole('Asesor')) {
            $asesor = Asesor::where('user_id', $user->id)->first();
            return $asesor ? $asesor->id : null;
        }
        return null;
    }
    protected function replacePlaceholders(string $sql, $user): string
{

    if ($user->hasRole('Asesor')) {
        $asesorId = $this->getAsesorId($user);
        if (!$asesorId) {
            throw new \Exception('No se encontró el ID del asesor.');
        }
        $sql = str_replace('{asesor.id}', (string)$asesorId, $sql);
    }

    return $sql;
}


    protected function loadConsultas(): void
    {
        $user = Auth::user();
        $rolesSupervisores = ['super_admin'];
        $rolesConAccesoCondicional = ['Jefe de operaciones', 'Jefe de creditos'];

        if ($user->hasAnyRole($rolesSupervisores)) {

            $this->consultas = ConsultaAsistente::latest()->take(10)->get();
        } elseif ($user->hasAnyRole($rolesConAccesoCondicional)) {

            if ($this->tieneRegistrosAsignados($user)) {

                $this->consultas = ConsultaAsistente::where('user_id', $user->id)
                    ->latest()
                    ->take(10)
                    ->get();
            } else {

                $this->consultas = ConsultaAsistente::latest()->take(10)->get();
            }
        } else {

            $this->consultas = ConsultaAsistente::where('user_id', $user->id)
                ->latest()
                ->take(10)
                ->get();
        }
    }

    public function clientesParaUsuario($user)
    {
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return Cliente::all();
        }
        if ($user->hasRole('Asesor')) {
            $asesorId = $this->getAsesorId($user);
            if ($asesorId) {
                return Cliente::where('asesor_id', $asesorId)->get();
            }
        }
        return collect();
    }

    public function gruposParaUsuario($user)
    {
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return Grupo::all();
        }
        if ($user->hasRole('Asesor')) {
            $asesorId = $this->getAsesorId($user);
            if ($asesorId) {
                return Grupo::where('asesor_id', $asesorId)->get();
            }
        }
        return collect();
    }

    public function prestamosParaUsuario($user)
    {
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return Prestamo::all();
        }
        if ($user->hasRole('Asesor')) {
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
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return Pago::all();
        }
        if ($user->hasRole('Asesor')) {
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
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
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
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return Mora::all();
        }
        if ($user->hasRole('Asesor')) {
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
        if ($user->hasAnyRole(['super_admin', 'Jefe de operaciones', 'Jefe de creditos'])) {
            return true;
        }
        if ($user->hasRole('Asesor')) {
            $asesorId = $this->getAsesorId($user);
            if ($asesorId) {
                return Cliente::where('asesor_id', $asesorId)->exists() ||
                       Grupo::where('asesor_id', $asesorId)->exists() ||
                       Prestamo::whereHas('grupo', fn($q) => $q->where('asesor_id', $asesorId))->exists();
            }
        }
        return false;
    }

    protected function filtrarPorAsesor($modelClass, $user)
    {
        if (!$user->hasRole('Asesor')) return $modelClass::query();

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
            case Pago::class:
                return $modelClass::whereHas('cuotaGrupal.prestamo.grupo', fn($q) => $q->where('asesor_id', $asesorId));
            case Mora::class:
                return $modelClass::whereHas('cuotaGrupal.prestamo.grupo', fn($q) => $q->where('asesor_id', $asesorId));

            default:
                return $modelClass::query();
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
                                ->afterStateUpdated(fn ($state) => $this->query = $state ?? ''),

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
            'activeTab' => 0]);
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

$esquema = Storage::get('esquema_bd.txt');
 $relaciones = <<<EOT
(Relaciones clave entre tablas)
- Un cliente pertenece a un asesor (`clientes.asesor_id = asesores.id`)
- Un grupo tiene un asesor (`grupos.asesor_id = asesores.id`)
- Un grupo tiene muchos clientes a través de `grupo_cliente`
- Un grupo tiene préstamos (`prestamos.grupo_id = grupos.id`)
- Un préstamo tiene cuotas (`cuotas_grupales.prestamo_id = prestamos.id`)
- Una cuota tiene pagos y moras (`pagos.cuota_grupal_id`, `moras.cuota_grupal_id`)
- Un préstamo tiene préstamos individuales por cliente (`prestamo_individual`)
Definición de cuotas en mora:
- Una cuota se considera en mora si tiene un registro en la tabla `moras` donde:
  - `estado_mora` es 'pendiente' o 'parcialmente_pagada'.
  - Se puede acceder a la cuota mediante `cuota_grupal_id`.
- El campo `cuotas_grupales.estado_cuota_grupal` también puede marcar estado de mora, pero la tabla `moras` contiene los detalles reales del atraso.
Manejo de preguntas no válidas o irrelevantes:
- Si la pregunta del usuario no tiene sentido, no está relacionada con datos financieros o no se puede responder con una consulta SELECT, responde amablemente diciendo que no se puede procesar esa solicitud.
- No inventes consultas si no estás seguro de cómo estructurarlas correctamente.
- Si la pregunta es ambigua, responde brevemente y solicita más detalles antes de generar la consulta.
Nota: El monto total de mora no está almacenado; se estima multiplicando los días de atraso (calculados desde el día siguiente a la fecha_vencimiento hasta la fecha_atraso) por el número de integrantes del grupo, considerando 1 sol por persona por día.

EOT;
$reglasPagos = <<<EOT
Reglas clave para responder sobre pagos:
- Solo se deben sumar los pagos que tengan `estado_pago = 'aprobado'`.
- Si el usuario menciona un mes (ej. junio), usa `MONTH(fecha_pago) = 6`.
- Si el usuario tiene el rol de **asesor**, filtra solo pagos de sus grupos: `g.asesor_id = {asesor.id}`.
- Ignora pagos con estado `'rechazado'`, `'pendiente'` u otros diferentes a `'aprobado'`.
- Siempre responde en lenguaje natural. No muestres código SQL.
- Si no hay pagos aprobados en el mes consultado, responde “Tus grupos no registran pagos aprobados en ese mes.”
EOT;

$reglasGenerales = <<<EOT
⚠️ Reglas estrictas:
- Nunca inventes nombres de columnas. Usa únicamente los que aparecen exactamente en el esquema.
- No utilices campos genéricos como "monto", "valor", "nombre_cliente", si no están definidos.
- Usa las siguientes equivalencias para evitar confusión:
    - "monto prestado" → `monto_prestado_individual`
    - "monto total del préstamo grupal" → `monto_prestado_total`
    - "monto de la cuota grupal" → `monto_cuota_grupal`
    - "monto pagado" → `monto_pagado`
- Si el usuario tiene rol asesor, no muestres datos de otros asesores.
- No uses `ORDER BY ... LIMIT 1` si hay posibilidad de empate en el valor máximo.
- Usa una subconsulta con `WHERE campo = (SELECT MAX(...))` para incluir todos los registros empatados.

EOT;


 $ejemplosSQL = <<<EOT
Ejemplos de consultas SQL:

1. Total de pagos por grupo:
<SQL>
SELECT g.nombre_grupo, SUM(p.monto_pagado) AS total_pagado
FROM grupos g
JOIN prestamos pr ON pr.grupo_id = g.id
JOIN cuotas_grupales cg ON cg.prestamo_id = pr.id
JOIN pagos p ON p.cuota_grupal_id = cg.id
GROUP BY g.nombre_grupo;
</SQL>

2. Clientes con moras activas:
<SQL>
SELECT c.id, pe.nombre, pe.apellidos, m.fecha_atraso
FROM clientes c
JOIN personas pe ON pe.id = c.persona_id
JOIN grupo_cliente gc ON gc.cliente_id = c.id
JOIN grupos g ON g.id = gc.grupo_id
JOIN prestamos pr ON pr.grupo_id = g.id
JOIN cuotas_grupales cg ON cg.prestamo_id = pr.id
JOIN moras m ON m.cuota_grupal_id = cg.id
WHERE m.estado_mora = 'activo';
</SQL>

-- Ejemplo para asesores:
<SQL>
SELECT cg.id, cg.numero_cuota, cg.fecha_vencimiento, m.estado_mora
FROM moras m
JOIN cuotas_grupales cg ON cg.id = m.cuota_grupal_id
JOIN prestamos p ON p.id = cg.prestamo_id
JOIN grupos g ON g.id = p.grupo_id
WHERE m.estado_mora IN ('pendiente', 'parcialmente_pagada')
AND g.asesor_id = {asesor.id};
</SQL>

<SQL>
SELECT
    SUM(
        DATEDIFF(m.fecha_atraso, DATE_ADD(cg.fecha_vencimiento, INTERVAL 1 DAY)) * (
            SELECT COUNT(*)
            FROM grupo_cliente gc
            WHERE gc.grupo_id = g.id
        )
    ) AS monto_total_estimado_mora
FROM moras m
JOIN cuotas_grupales cg ON cg.id = m.cuota_grupal_id
JOIN prestamos p ON p.id = cg.prestamo_id
JOIN grupos g ON g.id = p.grupo_id
WHERE m.estado_mora IN ('pendiente', 'parcialmente_pagada');
</SQL>


4. Cuotas en mora con nombre del grupo (asesor):
<SQL>
SELECT
    cg.id,
    cg.numero_cuota,
    cg.fecha_vencimiento,
    m.estado_mora,
    g.nombre_grupo
FROM moras m
JOIN cuotas_grupales cg ON cg.id = m.cuota_grupal_id
JOIN prestamos p ON p.id = cg.prestamo_id
JOIN grupos g ON g.id = p.grupo_id
WHERE m.estado_mora = 'pendiente'
AND g.asesor_id = {asesor.id};
</SQL>

5. Monto estimado de mora por grupo (asesor):
<SQL>
SELECT
    g.nombre_grupo,
    SUM(
        DATEDIFF(m.fecha_atraso, DATE_ADD(cg.fecha_vencimiento, INTERVAL 1 DAY)) *
        (SELECT COUNT(*) FROM grupo_cliente gc WHERE gc.grupo_id = g.id)
    ) AS monto_estimado_mora
FROM moras m
JOIN cuotas_grupales cg ON cg.id = m.cuota_grupal_id
JOIN prestamos p ON p.id = cg.prestamo_id
JOIN grupos g ON g.id = p.grupo_id
WHERE m.estado_mora IN ('pendiente', 'parcialmente_pagada')
AND g.asesor_id = {asesor.id}
GROUP BY g.nombre_grupo;
</SQL>


EOT;
$guiaRoles = <<<EOT
Guía de acceso según rol:
- Asesores: solo ven datos de sus clientes, grupos y pagos asignados.
- Jefes de operaciones y créditos: acceso total a toda la información.
- El usuario actual puede consultar solo datos dentro de su alcance.

Filtra adecuadamente si es asesor, usando:
- clientes.asesor_id = {asesor.id}
- grupos.asesor_id = {asesor.id}
EOT;

       $systemPrompt = <<<EOT
Eres un asistente virtual experto en finanzas, encargado de responder consultas SQL seguras (solo SELECT) sobre una base de datos financiera real.
Tu función es ayudar a los usuarios a obtener información útil sobre clientes, cuotas, préstamos, pagos, moras, grupos y asesores.

Esquema de base de datos:
{$esquema}

Relaciones clave:
{$relaciones}

Reglas pagos:
{$reglasPagos}

Ejemplos de consultas:
{$ejemplosSQL}

Reglas de uso:
- Solo consultas SELECT, nada que modifique la base de datos.
- Responde brevemente con explicación + consulta SQL entre <SQL>...</SQL>.
- Considera los roles: asesores solo ven su información; jefes pueden ver todo.
- Usa campos reales (por ejemplo, 'name' en users, no 'username').
- Usa JOINs adecuados según las relaciones.
- Las respuestas deben ser breves, claras y enfocadas en el objetivo del usuario.

{$guiaRoles}
{$reglasGenerales}

{$comentario}

Resumen de contexto del usuario actual:
- Clientes asignados: {$clientesCount} (Ej: {$nombresClientes})
- Grupos asignados: {$gruposCount} (Ej: {$nombresGrupos})
- Pagos totales: S/ {$totalPagos}, Cuotas totales: S/ {$totalCuotas}
- Moras activas: {$totalMoras}, Total préstamos: {$totalPrestamos}
EOT;

        $userPrompt = <<<EOT
Usuario: "{$user->name}" ("{$user->getRoleNames()->implode(', ')}")
Pregunta: "{$query}"
EOT;

        $response = $this->callOpenAI($systemPrompt, $userPrompt);
        Log::info("Respuesta OpenAI para usuario {$user->id}: {$response}");


if (preg_match('/<SQL>(.*?)<\/SQL>/is', $response, $matches)) {
    $sql = trim($matches[1]);

    try {
        $sql = $this->replacePlaceholders($sql, $user);
        Log::info("SQL ejecutado: {$sql}");
        if (!$this->isSafeSql($sql)) {
            return 'Consulta rechazada por motivos de seguridad.';
        }

        $result = DB::select($sql);

        if (empty($result)) {
            return 'Consulta ejecutada correctamente. No se encontraron resultados.';
        }

        $firstRow = $result[0];
        $fields = array_keys((array)$firstRow);
        if (count($result) === 1 && count($fields) === 1) {
            $fieldName = $fields[0];
            $value = $firstRow->$fieldName;
            switch ($fieldName) {
                case 'total_grupos':
                    return "Tienes {$value} grupos";
                case 'total_clientes':
                    return "Tienes {$value} clientes";
                case 'total_pagos':
                    return "Hay un total de {$value} pagos registrados";
                default:
                    return "{$fieldName}: {$value}";
            }
        }

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
