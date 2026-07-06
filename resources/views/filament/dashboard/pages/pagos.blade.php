@php
    use App\Models\Grupo;
    $user = auth()->user();

    $gruposQuery = Grupo::query();

    if ($user && $user->hasRole('Asesor')) {
        $gruposQuery->where('asesor_id', $user->id);
    }

    $listaGrupos = $gruposQuery->orderBy('nombre_grupo')->pluck('nombre_grupo', 'id');
@endphp

<form method="GET" action="{{ route('pagos.exportar.pdf') }}">
    <select name="grupo" class="rounded-lg ...">
        <option value="">Todos</option>
        @foreach($listaGrupos as $id => $nombreGrupo)
            <option value="{{ $id }}" {{ request('grupo') == $id ? 'selected' : '' }}>
                {{ $nombreGrupo }}
            </option>
        @endforeach
    </select>

    <input type="date" name="from" value="{{ request('from') }}">
    <input type="date" name="until" value="{{ request('until') }}">
    <select name="estado_pago">
        <option value="">Todos</option>
         <option value="aprobado" {{ request('estado_pago') == 'aprobado' ? 'selected' : '' }}>Aprobado</option>
        <option value="pendiente" {{ request('estado_pago') == 'pendiente' ? 'selected' : '' }}>Pendiente</option>
        <option value="rechazado" {{ request('estado_pago') == 'rechazado' ? 'selected' : '' }}>Rechazado</option>
    </select>

    <button type="submit">Exportar PDF</button>
</form>
