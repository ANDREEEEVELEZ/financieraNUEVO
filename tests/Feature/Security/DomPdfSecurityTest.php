<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * PR-2c | Task 4.3 – DomPDF must have PHP execution disabled.
 * This prevents server-side template injection via crafted PDF templates.
 */
class DomPdfSecurityTest extends TestCase
{
    public function test_dompdf_php_execution_disabled_in_config(): void
    {
        // config/dompdf.php must exist and have enable_php set to false
        $enablePhp = config('dompdf.options.enable_php', config('dompdf.enable_php', null));

        $this->assertFalse(
            (bool) $enablePhp,
            'DomPDF isPhpEnabled must be false — PHP execution in PDF templates is an RCE vector'
        );
    }

    public function test_dompdf_remote_disabled_in_config(): void
    {
        $enableRemote = config('dompdf.options.enable_remote', config('dompdf.enable_remote', null));

        $this->assertFalse(
            (bool) $enableRemote,
            'DomPDF enable_remote must be false to prevent SSRF via PDF templates'
        );
    }
}
