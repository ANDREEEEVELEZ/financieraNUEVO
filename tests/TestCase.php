<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseRoles();
    }

    protected function seedBaseRoles(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        foreach (['super_admin', 'Jefe de creditos', 'Jefe de operaciones', 'Asesor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
