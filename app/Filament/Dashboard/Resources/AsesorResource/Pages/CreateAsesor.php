<?php

namespace App\Filament\Dashboard\Resources\AsesorResource\Pages;

use App\Filament\Dashboard\Resources\AsesorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

class CreateAsesor extends CreateRecord
{
    protected static string $resource = AsesorResource::class;

    protected function afterCreate(): void
    {
        // Asignar el rol de Asesor al usuario asociado
        if ($this->record && $this->record->user) {
            $role = Role::findByName('Asesor');
            $this->record->user->assignRole($role);
        }
    }
}
