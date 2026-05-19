<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Http\Requests\Auth\AuthRequest;
use App\Http\Requests\Prestamo\UpdatePrestamoRequest;
use App\Http\Requests\Cliente\UpdateClienteRequest;

/**
 * PR-2b | Task 3.7 – FormRequest classes must exist and define validation rules.
 * These are unit-style checks — we verify the class structure and rules.
 */
class FormRequestTest extends TestCase
{
    public function test_auth_request_class_exists(): void
    {
        $this->assertTrue(
            class_exists(AuthRequest::class),
            'AuthRequest class must exist under App\Http\Requests\Auth'
        );
    }

    public function test_auth_request_defines_rules(): void
    {
        $request = new AuthRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('email', $rules, 'AuthRequest must validate email');
        $this->assertArrayHasKey('password', $rules, 'AuthRequest must validate password');
    }

    public function test_update_prestamo_request_class_exists(): void
    {
        $this->assertTrue(
            class_exists(UpdatePrestamoRequest::class),
            'UpdatePrestamoRequest class must exist under App\Http\Requests\Prestamo'
        );
    }

    public function test_update_prestamo_request_defines_estado_rule(): void
    {
        $request = new UpdatePrestamoRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('estado', $rules, 'UpdatePrestamoRequest must validate estado');
    }

    public function test_update_cliente_request_class_exists(): void
    {
        $this->assertTrue(
            class_exists(UpdateClienteRequest::class),
            'UpdateClienteRequest class must exist under App\Http\Requests\Cliente'
        );
    }
}
