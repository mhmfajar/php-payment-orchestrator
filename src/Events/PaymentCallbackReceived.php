<?php

namespace Mhmfajar\PaymentOrchestrator\Events;

use Mhmfajar\PaymentOrchestrator\DTO\CallbackResponse;

/**
 * Event fired after a gateway callback has been verified and normalized.
 */
class PaymentCallbackReceived
{
    /**
     * Normalized callback response.
     *
     * @var CallbackResponse
     */
    public $response;

    /**
     * Create the event with callback response context.
     *
     * @param CallbackResponse $response Normalized callback response.
     * @return void
     */
    public function __construct(CallbackResponse $response)
    {
        $this->response = $response;
    }
}
