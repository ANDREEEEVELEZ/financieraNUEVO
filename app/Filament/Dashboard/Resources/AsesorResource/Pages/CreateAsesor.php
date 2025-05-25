<?php

namespace App\Filament\Dashboard\Resources\AsesorResource\Pages;

use App\Filament\Dashboard\Resources\AsesorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAsesor extends CreateRecord
{
    // Definir correctamente la propiedad $resource
    protected static string $resource = AsesorResource::class;

}
