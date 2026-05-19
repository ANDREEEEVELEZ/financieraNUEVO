<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that replaces sensitive context keys with [REDACTED].
 *
 * Walks context recursively so nested structures are also covered.
 * Used directly in unit tests and pushed onto the logger by RedactSensitiveProcessor.
 */
final class SensitiveKeyRedactor implements ProcessorInterface
{
    /** @var list<string> Keys whose values must never appear in logs */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'api_key',
        '_token',
        'csrf',
        'banco_cuenta',
        'numero_cuenta',
        'cuenta_bancaria',
        'cedula',
        'dni',
        'DNI',
        'ruc',
        'RUC',
        'email',
        'correo',
        'celular',
        'telefono',
        'phone',
        'direccion',
        'nombre_completo',
        'saldo',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(context: $this->redact($record->context));
    }

    /** @param array<mixed> $data */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::SENSITIVE_KEYS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
