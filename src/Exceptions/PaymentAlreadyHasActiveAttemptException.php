<?php

namespace Mhmfajar\PaymentOrchestrator\Exceptions;

/**
 * Reserved for flows that need to reject duplicate active payment attempts explicitly.
 */
class PaymentAlreadyHasActiveAttemptException extends PaymentException
{
}
