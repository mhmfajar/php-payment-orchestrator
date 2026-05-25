<?php

namespace Mhmfajar\PaymentOrchestrator\Events;

/**
 * Event fired when the orchestrator skips a failed gateway and tries the next one.
 */
class PaymentFallbackTriggered
{
    /**
     * Payment row being retried through fallback.
     *
     * @var array
     */
    public $payment;

    /**
     * Gateway that failed before fallback.
     *
     * @var string
     */
    public $gateway;

    /**
     * Normalized reason that allowed fallback.
     *
     * @var string|null
     */
    public $failureReason;

    /**
     * Create the event with payment, gateway, and failure context.
     *
     * @param array $payment Payment row.
     * @param string $gateway Gateway name.
     * @param string|null $failureReason Normalized failure reason.
     * @return void
     */
    public function __construct(array $payment, $gateway, $failureReason)
    {
        $this->payment = $payment;
        $this->gateway = $gateway;
        $this->failureReason = $failureReason;
    }
}
