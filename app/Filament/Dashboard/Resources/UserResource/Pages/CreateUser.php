<?php

namespace App\Filament\Dashboard\Resources\UserResource\Pages;

use App\Filament\Dashboard\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
