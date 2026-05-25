<?php

namespace Mhmfajar\PaymentOrchestrator\Exceptions;

/**
 * Raised when fallback would violate duplicate-payment safety rules.
 */
class FallbackNotAllowedException extends PaymentException
{
}
