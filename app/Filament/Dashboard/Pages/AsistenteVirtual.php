<?php

namespace App\Filament\Dashboard\Pages;

use App\Models\consulta_asistente;
use App\Models\ConsultaAsistente;
use Filament\Pages\Page;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Collection;

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
        $this->consultas = ConsultaAsistente::latest()->take(10)->get();

        $this->form->fill([
            'query' => '',
            'response' => '',
            'activeTab' => 0,
        ]);

        $this->activeTab = 0;
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
                                    ->color('primary')
                            ])->columnSpan(2)->alignment('right'),
                        ]),

                    Tabs\Tab::make('Historial')
                        ->schema([
                            \Filament\Forms\Components\View::make('filament.dashboard.pages.historial-table')
                                ->label('Últimas consultas')
                                ->viewData(['consultas' => $this->consultas]),
                        ]),
                ])
                ->activeTab(1) // 1-based index: 1 = "Asistente" tab
                ->reactive()
                ->afterStateUpdated(fn ($state) => $this->form->fill(['activeTab' => $state - 1])),
        ];
    }

    public function submitQuery()
    {
        if (empty($this->query)) {
            $this->notify('error', 'La consulta no puede estar vacía.');
            return;
        }

        $this->response = $this->processQuery($this->query);

        ConsultaAsistente::create([
            'consulta' => $this->query,
            'respuesta' => $this->response,
        ]);

        $this->consultas = ConsultaAsistente::latest()->take(10)->get();
        $this->reset(['query', 'response']);
        $this->form->fill(['activeTab' => 0]);
        $this->activeTab = 0;
    }

    protected function processQuery($query): string
    {
        return "Respuesta a: " . $query;
    }
}