<?php

namespace Mhmfajar\PaymentOrchestrator\Events;

/**
 * Event fired when a callback marks a payment as expired.
 */
class PaymentExpired
{
    /**
     * Payment row marked as expired.
     *
     * @var array
     */
    public $payment;

    /**
     * Create the event with payment context.
     *
     * @param array $payment Payment row.
     * @return void
     */
    public function __construct(array $payment)
    {
        $this->payment = $payment;
    }
}
