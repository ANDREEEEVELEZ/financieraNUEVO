<?php

namespace App\Filament\Dashboard\Pages;

use App\Models\consulta_asistente;
use Filament\Pages\Page;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AsistenteVirtual extends Page
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static string $view = 'filament.dashboard.pages.asistente-virtual';
    protected static ?string $navigationLabel = 'Asistente Virtual';

    public $query = '';
    public $response = '';
    public Collection $consultas;
    public $activeTab = 0;

    public function mount(): void
    {
        $this->activeTab = 0;
        $this->consultas = consulta_asistente::latest()->take(10)->get();
    }

    protected function getFormSchema(): array
    {
        return [
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
                                    ->color('primary')
                            ])->columnSpan(2)->alignment('right'),
                        ]),

                    Tabs\Tab::make('Historial')
                        ->schema([
                            \Filament\Forms\Components\View::make('filament.dashboard.pages.historial-table')
                                ->label('Últimas consultas'),
                        ]),
                ])
                ->activeTab($this->activeTab)
                ->persistTab()
                ->reactive()
                ->afterStateUpdated(function ($state) {
                    $this->activeTab = $state;
                    Log::info('ActiveTab changed via afterStateUpdated to: ' . $state);
                }),
        ];
    }

    protected function getFormModel(): string
    {
        return consulta_asistente::class;
    }

    public function submitQuery()
    {
        if (empty($this->query)) {
            $this->notify('error', 'La consulta no puede estar vacía.');
            return;
        }

        $this->response = $this->processQuery($this->query);

        consulta_asistente::create([
            'consulta' => $this->query,
            'respuesta' => $this->response,
        ]);

        $this->consultas = consulta_asistente::latest()->take(10)->get();
        $this->reset(['query', 'response']);
        $this->activeTab = 0;
    }

    protected function processQuery($query): string
    {
        return "Respuesta a: " . $query;
    }

    public function updatedActiveTab($value)
    {
        $this->activeTab = $value;
        Log::info('ActiveTab updated to: ' . $value);
    }
}
