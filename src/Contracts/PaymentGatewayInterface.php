<?php

namespace Mhmfajar\PaymentOrchestrator\Contracts;

use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;
use Mhmfajar\PaymentOrchestrator\DTO\CallbackResponse;
use Mhmfajar\PaymentOrchestrator\DTO\GatewayHealthResponse;

/**
 * Defines the gateway operations the orchestrator can call without framework or SDK coupling.
 */
interface PaymentGatewayInterface
{
    /**
     * Create a payment transaction or return a normalized failure response.
     *
     * @param PaymentRequest $request Normalized payment creation request.
     * @return PaymentResponse Normalized gateway payment response.
     */
    public function createPayment(PaymentRequest $request);

    /**
     * Retrieve gateway status for an order.
     *
     * @param string $orderId Application order identifier.
     * @return PaymentResponse|array|null Gateway-specific status response.
     */
    public function getStatus($orderId);

    /**
     * Verify, parse, and normalize a callback payload.
     *
     * @param array $payload Raw gateway callback payload.
     * @return CallbackResponse Normalized callback response.
     */
    public function handleCallback(array $payload);

    /**
     * Verify callback authenticity.
     *
     * @param array $payload Raw gateway callback payload.
     * @return bool True when the callback passes verification.
     */
    public function verifyCallback(array $payload);

    /**
     * Check whether the gateway is available before creating payment.
     *
     * @return GatewayHealthResponse Gateway health response.
     */
    public function healthCheck();

    /**
     * Return the gateway identifier used in config and attempts.
     *
     * @return string Gateway name.
     */
    public function getName();
}
