<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * PR-2c | Task 4.5 – AsistenteVirtual (AI SQL executor) must be removed.
 * The page and controller expose raw SQL execution via AI prompts — an RCE/prompt-injection vector.
 *
 * We use class_exists() with explicit strings (not 'use' imports) so the test
 * does not accidentally trigger autoloading of a class that should not exist.
 */
class AsistenteVirtualTest extends TestCase
{
    public function test_asistente_virtual_class_does_not_exist(): void
    {
        // class_exists() with autoload=false: do NOT trigger the autoloader
        $this->assertFalse(
            class_exists('App\\Filament\\Dashboard\\Pages\\AsistenteVirtual', false),
            'AsistenteVirtual page class must be deleted — it is an AI SQL executor'
        );
    }

    public function test_asistente_controller_class_does_not_exist(): void
    {
        $this->assertFalse(
            class_exists('App\\Http\\Controllers\\AsistenteController', false),
            'AsistenteController class must be deleted — guardarEsquemaEnArchivo is a schema disclosure vector'
        );
    }

    public function test_asistente_virtual_file_does_not_exist(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Filament/Dashboard/Pages/AsistenteVirtual.php'),
            'AsistenteVirtual.php file must be deleted from the repository'
        );
    }

    public function test_asistente_controller_file_does_not_exist(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Http/Controllers/AsistenteController.php'),
            'AsistenteController.php file must be deleted from the repository'
        );
    }
}
