<?php

namespace Mhmfajar\PaymentOrchestrator\Support;

use Psr\Log\AbstractLogger;

/**
 * PSR-3 compatible no-op logger used when no application logger is supplied.
 */
class NullLogger extends AbstractLogger
{
    /**
     * Ignore log messages.
     *
     * @param string $level PSR-3 log level.
     * @param string|\Stringable $message Log message.
     * @param array $context Log context.
     * @return null
     */
    public function log($level, $message, array $context = array())
    {
        return null;
    }
}
