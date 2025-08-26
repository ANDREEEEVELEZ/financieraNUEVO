<?php

namespace App\Filament\Dashboard\Resources\EgresosResource\Pages;

use App\Filament\Dashboard\Resources\EgresosResource;
use App\Filament\Dashboard\Resources\EgresosResource\Widgets\EgresosStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;

class ListEgresos extends ListRecords
{
    protected static string $resource = EgresosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle'),

            // Botón de exportación
            Actions\Action::make('exportar')
                ->label('Exportar Egresos')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->form([
                    Forms\Components\Section::make('Configuración de Exportación')
                        ->description('Seleccione el formato y filtros para la exportación')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\Select::make('formato')
                                        ->label('Formato de Exportación')
                                        ->options([
                                            'pdf' => '� PDF',
                                            'excel' => '� Excel (.csv)'
                                        ])
                                        ->default('pdf')
                                        ->required()
                                        ->native(false),

                                    Forms\Components\Select::make('tipo_egreso')
                                        ->label('Tipo de Egreso')
                                        ->options([
                                            'todos' => '📋 Todos los tipos',
                                            'desembolso' => '💰 Solo Desembolsos',
                                            'gasto' => '💸 Solo Gastos'
                                        ])
                                        ->default('todos')
                                        ->required()
                                        ->native(false),
                                ]),

                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\DatePicker::make('fecha_desde')
                                        ->label('Fecha Desde')
                                        ->helperText('Dejar vacío para incluir desde el principio')
                                        ->maxDate(now()),

                                    Forms\Components\DatePicker::make('fecha_hasta')
                                        ->label('Fecha Hasta')
                                        ->helperText('Dejar vacío para incluir hasta la fecha actual')
                                        ->maxDate(now()),
                                ]),

                            Forms\Components\Placeholder::make('info')
                                ->content('💡 **Información importante:**
• Si no selecciona fechas, se exportarán todos los registros
• El archivo se descargará automáticamente una vez generado')
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (array $data) {
                    // Construir la URL con los parámetros
                    $params = http_build_query([
                        'formato' => $data['formato'],
                        'tipo_egreso' => $data['tipo_egreso'],
                        'fecha_desde' => $data['fecha_desde'] ?? null,
                        'fecha_hasta' => $data['fecha_hasta'] ?? null,
                    ]);

                    $url = route('egresos.exportar') . '?' . $params;

                    // Redirigir para descargar el archivo
                    return redirect($url);
                })
                ->modalHeading('📊 Exportar Egresos')
                ->modalSubmitActionLabel('Generar y Descargar')
                ->modalCancelActionLabel('Cancelar')
                ->modalWidth('2xl'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EgresosStatsWidget::class,
        ];
    }
}
