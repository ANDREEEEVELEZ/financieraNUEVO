<?php

namespace App\Filament\Dashboard\Resources\MoraResource\Pages;

use App\Filament\Dashboard\Resources\MoraResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMora extends EditRecord
{
    protected static string $resource = MoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
