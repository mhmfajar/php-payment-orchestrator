<?php

namespace Mhmfajar\PaymentOrchestrator\Gateways;

use Mhmfajar\PaymentOrchestrator\Constants\GatewayFailureReason;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;

/**
 * Midtrans gateway placeholder with callback signature verification support.
 */
class MidtransGateway extends AbstractGateway
{
    /**
     * Create a Midtrans payment response placeholder.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @return PaymentResponse Placeholder payment response.
     */
    public function createPayment(PaymentRequest $request)
    {
        // Real Snap/Core API calls belong here once credentials and HTTP transport are configured.
        return new PaymentResponse(array(
            'success' => false,
            'gateway' => $this->getName(),
            'order_id' => $request->getOrderId(),
            'message' => 'Midtrans driver is not fully implemented yet.',
            'failure_reason' => GatewayFailureReason::INVALID_GATEWAY_RESPONSE,
            'fallback_allowed' => true,
            'raw' => array(),
        ));
    }

    /**
     * Verify a Midtrans callback signature when a server key is configured.
     *
     * @param array $payload Raw callback payload.
     * @return bool True when callback verification passes.
     */
    public function verifyCallback(array $payload)
    {
        // Skip strict signature validation only when the application has not configured a server key.
        if (! isset($this->config['server_key']) || $this->config['server_key'] === '') {
            return parent::verifyCallback($payload);
        }

        if (! isset($payload['signature_key'], $payload['order_id'], $payload['status_code'], $payload['gross_amount'])) {
            return false;
        }

        $signature = hash('sha512', $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $this->config['server_key']);
        return hash_equals($signature, $payload['signature_key']);
    }

    /**
     * Return the configured gateway name.
     *
     * @return string
     */
    public function getName()
    {
        return 'midtrans';
    }
}
