<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * PII redaction for the Laravel logging system.
 *
 * Laravel log `tap` entry point — LogManager calls `__invoke(Logger)` when
 * building a channel. We push a Monolog processor onto the channel's handler.
 *
 * Sensitive keys are replaced with `[REDACTED]` to preserve log shape.
 */
final class RedactSensitiveProcessor
{
    /**
     * Laravel log tap entry point.
     * Called by LogManager when building a channel that lists this class in `tap`.
     */
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(new SensitiveKeyRedactor());
    }
}
