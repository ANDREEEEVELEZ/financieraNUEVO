<?php

namespace App\Filament\Dashboard\Resources\ProductoFinancieroResource\Pages;

use App\Filament\Dashboard\Resources\ProductoFinancieroResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductoFinanciero extends EditRecord
{
    protected static string $resource = ProductoFinancieroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
