<x-filament-panels::page class="fi-dashboard-page">
    @if (method_exists($this, 'filtersForm'))
        {{ $this->filtersForm }}
    @endif

    <div class="mb-8 animate__animated animate__fadeInDown">
        <div class="flex flex-col md:flex-row items-center gap-6 p-6 bg-gradient-to-r from-blue-100 via-indigo-100 to-purple-100 dark:from-blue-900 dark:via-indigo-900 dark:to-purple-900 rounded-2xl shadow-xl border border-blue-200 dark:border-blue-700">
            <div class="flex-shrink-0">
                <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name) }}&background=4f46e5&color=fff&size=96" alt="Avatar" class="rounded-full shadow-lg ring-4 ring-blue-300 dark:ring-blue-700 animate__animated animate__pulse animate__infinite" style="width:96px;height:96px;">
            </div>
            <div class="flex-1">
                <h2 class="text-2xl md:text-3xl font-extrabold text-blue-900 dark:text-blue-100 mb-1 animate__animated animate__fadeInLeft">¡Bienvenido, <span class="text-indigo-600 dark:text-indigo-300">{{ auth()->user()->name }}</span>!</h2>
                <p class="text-lg text-gray-700 dark:text-gray-200 animate__animated animate__fadeInLeft animate__delay-1s">Correo: <span class="font-semibold text-indigo-700 dark:text-indigo-300">{{ auth()->user()->email }}</span></p>
                
                <div class="mt-2 flex flex-wrap gap-2 animate__animated animate__fadeInLeft animate__delay-2s">
                    @php
                        $roles = auth()->user()->getRoleNames();
                    @endphp
                    @foreach($roles as $rol)
                        <span class="inline-block bg-gradient-to-r from-indigo-400 to-blue-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow animate__animated animate__bounceIn">{{ $rol }}</span>
                    @endforeach
                </div>
            </div>
            <div class="flex flex-col items-center animate__animated animate__fadeInRight animate__delay-1s">
                <svg class="w-10 h-10 text-indigo-400 dark:text-indigo-200 mb-2 animate__animated animate__heartBeat animate__infinite" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21C12 21 4 13.5 4 8.5C4 5.42 6.42 3 9.5 3C11.24 3 12.91 4.01 13.44 5.61C13.97 4.01 15.64 3 17.38 3C20.46 3 22.88 5.42 22.88 8.5C22.88 13.5 15 21 15 21H12Z" /></svg>
                <span class="text-xs text-indigo-700 dark:text-indigo-300 font-semibold">Usuario activo</span>
            </div>
        </div>
    </div>

    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :data="
            [
                ...(property_exists($this, 'filters') ? ['filters' => $this->filters] : []),
                ...$this->getWidgetData(),
            ]
        "
        :widgets="$this->getVisibleWidgets()"
    />
</x-filament-panels::page>
