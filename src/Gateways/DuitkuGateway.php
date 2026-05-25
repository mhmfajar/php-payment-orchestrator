<?php

namespace Mhmfajar\PaymentOrchestrator\Gateways;

use Mhmfajar\PaymentOrchestrator\Constants\GatewayFailureReason;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;

/**
 * Duitku gateway placeholder with callback signature verification support.
 */
class DuitkuGateway extends AbstractGateway
{
    /**
     * Create a Duitku payment response placeholder.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @return PaymentResponse Placeholder payment response.
     */
    public function createPayment(PaymentRequest $request)
    {
        // Real Duitku API calls belong here once credentials and HTTP transport are configured.
        return new PaymentResponse(array(
            'success' => false,
            'gateway' => $this->getName(),
            'order_id' => $request->getOrderId(),
            'message' => 'Duitku driver is not fully implemented yet.',
            'failure_reason' => GatewayFailureReason::INVALID_GATEWAY_RESPONSE,
            'fallback_allowed' => true,
            'raw' => array(),
        ));
    }

    /**
     * Verify a Duitku callback signature when an API key is configured.
     *
     * @param array $payload Raw callback payload.
     * @return bool True when callback verification passes.
     */
    public function verifyCallback(array $payload)
    {
        // Skip strict signature validation only when the application has not configured an API key.
        if (! isset($this->config['api_key']) || $this->config['api_key'] === '') {
            return parent::verifyCallback($payload);
        }

        if (! isset($payload['merchantCode'], $payload['amount'], $payload['merchantOrderId'], $payload['signature'])) {
            return false;
        }

        $signature = md5($payload['merchantCode'] . $payload['amount'] . $payload['merchantOrderId'] . $this->config['api_key']);
        return hash_equals($signature, $payload['signature']);
    }

    /**
     * Return the configured gateway name.
     *
     * @return string
     */
    public function getName()
    {
        return 'duitku';
    }
}
