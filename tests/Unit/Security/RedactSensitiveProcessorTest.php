<?php

namespace Tests\Unit\Security;

use App\Logging\SensitiveKeyRedactor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

/**
 * PR-2b | Task 3.1 – SensitiveKeyRedactor (Monolog processor) must redact sensitive keys.
 * RedactSensitiveProcessor is the Laravel tap wrapper; SensitiveKeyRedactor is the Monolog processor.
 */
class RedactSensitiveProcessorTest extends TestCase
{
    private SensitiveKeyRedactor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new SensitiveKeyRedactor();
    }

    private function makeRecord(array $context = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'testing',
            level: Level::Debug,
            message: 'test message',
            context: $context,
        );
    }

    public function test_email_is_redacted(): void
    {
        $record = $this->makeRecord(['email' => 'user@example.com']);
        $processed = ($this->processor)($record);

        $this->assertSame('[REDACTED]', $processed->context['email']);
    }

    public function test_password_is_redacted(): void
    {
        $record = $this->makeRecord(['password' => 'supersecret']);
        $processed = ($this->processor)($record);

        $this->assertSame('[REDACTED]', $processed->context['password']);
    }

    public function test_banco_cuenta_is_redacted(): void
    {
        $record = $this->makeRecord(['banco_cuenta' => '123-456-789']);
        $processed = ($this->processor)($record);

        $this->assertSame('[REDACTED]', $processed->context['banco_cuenta']);
    }

    public function test_cedula_is_redacted(): void
    {
        $record = $this->makeRecord(['cedula' => '12345678']);
        $processed = ($this->processor)($record);

        $this->assertSame('[REDACTED]', $processed->context['cedula']);
    }

    public function test_token_is_redacted(): void
    {
        $record = $this->makeRecord(['token' => 'abc123xyz']);
        $processed = ($this->processor)($record);

        $this->assertSame('[REDACTED]', $processed->context['token']);
    }

    public function test_safe_keys_are_preserved(): void
    {
        $record = $this->makeRecord([
            'user_id' => 42,
            'action'  => 'canAccessPanel',
        ]);
        $processed = ($this->processor)($record);

        $this->assertSame(42, $processed->context['user_id']);
        $this->assertSame('canAccessPanel', $processed->context['action']);
    }

    public function test_multiple_sensitive_keys_in_one_record(): void
    {
        $record = $this->makeRecord([
            'email'        => 'user@example.com',
            'password'     => 'secret',
            'banco_cuenta' => '111',
            'user_id'      => 7,
        ]);
        $processed = ($this->processor)($record);

        $this->assertSame('[REDACTED]', $processed->context['email']);
        $this->assertSame('[REDACTED]', $processed->context['password']);
        $this->assertSame('[REDACTED]', $processed->context['banco_cuenta']);
        $this->assertSame(7, $processed->context['user_id']);
    }

    public function test_new_financial_keys_are_redacted(): void
    {
        $record = $this->makeRecord([
            'dni'            => '12345678',
            'celular'        => '987654321',
            'saldo'          => '1500.00',
            'numero_cuenta'  => '001-123456789',
            'cuenta_bancaria' => '002-987654321',
        ]);
        $processed = ($this->processor)($record);

        $this->assertSame('[REDACTED]', $processed->context['dni']);
        $this->assertSame('[REDACTED]', $processed->context['celular']);
        $this->assertSame('[REDACTED]', $processed->context['saldo']);
        $this->assertSame('[REDACTED]', $processed->context['numero_cuenta']);
        $this->assertSame('[REDACTED]', $processed->context['cuenta_bancaria']);
    }

    public function test_nested_sensitive_keys_are_redacted(): void
    {
        $record = $this->makeRecord([
            'user' => [
                'email'    => 'nested@example.com',
                'password' => 'nested_secret',
                'name'     => 'John',
            ],
            'action' => 'login',
        ]);
        $processed = ($this->processor)($record);

        $this->assertSame('[REDACTED]', $processed->context['user']['email']);
        $this->assertSame('[REDACTED]', $processed->context['user']['password']);
        $this->assertSame('John', $processed->context['user']['name']);
        $this->assertSame('login', $processed->context['action']);
    }

    public function test_deeply_nested_sensitive_keys_are_redacted(): void
    {
        $record = $this->makeRecord([
            'request' => [
                'body' => [
                    'credentials' => [
                        'password' => 'deep_secret',
                        'token'    => 'deep_token',
                    ],
                ],
                'meta' => 'safe',
            ],
        ]);
        $processed = ($this->processor)($record);

        $this->assertSame('[REDACTED]', $processed->context['request']['body']['credentials']['password']);
        $this->assertSame('[REDACTED]', $processed->context['request']['body']['credentials']['token']);
        $this->assertSame('safe', $processed->context['request']['meta']);
    }

    public function test_non_array_values_are_not_affected_by_recursion(): void
    {
        $record = $this->makeRecord([
            'count'   => 5,
            'enabled' => true,
            'ratio'   => 0.75,
            'user_id' => 42,
        ]);
        $processed = ($this->processor)($record);

        $this->assertSame(5, $processed->context['count']);
        $this->assertSame(true, $processed->context['enabled']);
        $this->assertSame(0.75, $processed->context['ratio']);
        $this->assertSame(42, $processed->context['user_id']);
    }
}
