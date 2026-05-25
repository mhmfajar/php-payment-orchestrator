<?php

namespace Mhmfajar\PaymentOrchestrator\Exceptions;

/**
 * Raised when a gateway callback fails signature or authenticity checks.
 */
class InvalidCallbackSignatureException extends PaymentException
{
}
