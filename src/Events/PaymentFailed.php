<?php

namespace Mhmfajar\PaymentOrchestrator\Events;

/**
 * Event fired when payment creation or callback processing results in failure.
 */
class PaymentFailed
{
    /**
     * Payment row marked as failed.
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
