<?php

namespace Mhmfajar\PaymentOrchestrator\Gateways;

use Mhmfajar\PaymentOrchestrator\Constants\GatewayFailureReason;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;

/**
 * Doku gateway placeholder kept extensible for checkout or payment APIs.
 */
class DokuGateway extends AbstractGateway
{
    /**
     * Create a Doku payment response placeholder.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @return PaymentResponse Placeholder payment response.
     */
    public function createPayment(PaymentRequest $request)
    {
        // Real Doku API calls belong here once credentials and HTTP transport are configured.
        return new PaymentResponse(array(
            'success' => false,
            'gateway' => $this->getName(),
            'order_id' => $request->getOrderId(),
            'message' => 'Doku driver is not fully implemented yet.',
            'failure_reason' => GatewayFailureReason::INVALID_GATEWAY_RESPONSE,
            'fallback_allowed' => true,
            'raw' => array(),
        ));
    }

    /**
     * Verify a Doku callback payload.
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
        return 'doku';
    }
}
