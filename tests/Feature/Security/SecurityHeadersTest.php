<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * PR-2a | Task 2.1 – Security headers must be present on all web responses.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_x_frame_options_header_present_on_login_page(): void
    {
        $response = $this->get('/dashboard/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_x_content_type_options_header_present(): void
    {
        $response = $this->get('/dashboard/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_strict_transport_security_header_present(): void
    {
        $response = $this->get('/dashboard/login');

        $this->assertTrue($response->headers->has('Strict-Transport-Security'));
        $this->assertStringContainsString('max-age=31536000', $response->headers->get('Strict-Transport-Security'));
    }

    public function test_referrer_policy_header_present(): void
    {
        $response = $this->get('/dashboard/login');

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_csp_is_enforced_not_report_only(): void
    {
        $response = $this->get('/dashboard/login');

        $this->assertTrue(
            $response->headers->has('Content-Security-Policy'),
            'Content-Security-Policy must be enforced (not Report-Only)'
        );
        $this->assertFalse(
            $response->headers->has('Content-Security-Policy-Report-Only'),
            'Content-Security-Policy-Report-Only must not be present in production mode'
        );
    }

    public function test_csp_contains_required_directives(): void
    {
        $response  = $this->get('/dashboard/login');
        $cspHeader = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $cspHeader);
        $this->assertStringContainsString("script-src", $cspHeader);
    }

    public function test_permissions_policy_header_present(): void
    {
        $response = $this->get('/dashboard/login');

        $this->assertTrue(
            $response->headers->has('Permissions-Policy'),
            'Permissions-Policy header must be present'
        );

        $policy = $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('payment=()', $policy);
    }
}
