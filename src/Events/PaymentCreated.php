<?php

namespace Mhmfajar\PaymentOrchestrator\Events;

/**
 * Event fired after a payable payment attempt is created and activated.
 */
class PaymentCreated
{
    /**
     * Payment row created or updated by the store.
     *
     * @var array
     */
    public $payment;

    /**
     * Active attempt row created for the payable transaction.
     *
     * @var array
     */
    public $attempt;

    /**
     * Create the event with payment and attempt context.
     *
     * @param array $payment Payment row.
     * @param array $attempt Attempt row.
     * @return void
     */
    public function __construct(array $payment, array $attempt)
    {
        $this->payment = $payment;
        $this->attempt = $attempt;
    }
}
