<?php

namespace Mhmfajar\PaymentOrchestrator\Gateways;

use Mhmfajar\PaymentOrchestrator\Constants\GatewayFailureReason;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;

/**
 * Xendit gateway placeholder kept extensible for invoice or payment-request APIs.
 */
class XenditGateway extends AbstractGateway
{
    /**
     * Create a Xendit payment response placeholder.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @return PaymentResponse Placeholder payment response.
     */
    public function createPayment(PaymentRequest $request)
    {
        // Real Xendit API calls belong here once credentials and HTTP transport are configured.
        return new PaymentResponse(array(
            'success' => false,
            'gateway' => $this->getName(),
            'order_id' => $request->getOrderId(),
            'message' => 'Xendit driver is not fully implemented yet.',
            'failure_reason' => GatewayFailureReason::INVALID_GATEWAY_RESPONSE,
            'fallback_allowed' => true,
            'raw' => array(),
        ));
    }

    /**
     * Verify a Xendit callback payload.
     *
     * @param array $payload Raw callback payload.
     * @return bool True when callback verification passes.
     */
    public function verifyCallback(array $payload)
    {
        return parent::verifyCallback($payload);
    }

    /**
     * Return the configured gateway name.
     *
     * @return string
     */
    public function getName()
    {
        return 'xendit';
    }
}
