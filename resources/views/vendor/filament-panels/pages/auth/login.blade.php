<x-filament-panels::page.simple>
    {{-- Eliminado encabezado por personalización --}}
    {{-- {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }} --}}

    <div class="flex flex-col items-center mb-6">
        <img src="/LogoEmprendeConmigo.png" alt="Logo Financiera" class="w-24 h-24 mb-2">
        <h2 class="text-2xl font-bold text-center text-gray-800 dark:text-white mb-2">FINANCIERA EMPRENDE CONMIGO</h2>
    </div>

    @if (session()->has('notification'))
        <div class="p-4 mb-4 text-sm rounded-lg {{ session('notification.status') === 'danger' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}" role="alert">
            <div class="font-bold">{{ session('notification.title') }}</div>
            <div>{{ session('notification.message') }}</div>
        </div>
    @endif

    <x-filament-panels::form id="form" wire:submit="authenticate">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
</x-filament-panels::page.simple>
