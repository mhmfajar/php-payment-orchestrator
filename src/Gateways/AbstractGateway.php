<?php

namespace Mhmfajar\PaymentOrchestrator\Gateways;

use Mhmfajar\PaymentOrchestrator\Contracts\PaymentGatewayInterface;
use Mhmfajar\PaymentOrchestrator\DTO\CallbackResponse;
use Mhmfajar\PaymentOrchestrator\DTO\GatewayHealthResponse;
use Mhmfajar\PaymentOrchestrator\Support\StatusMapper;

/**
 * Base gateway implementation with default callback parsing and health behavior.
 */
abstract class AbstractGateway implements PaymentGatewayInterface
{
    /**
     * Gateway-specific configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Gateway status mapper.
     *
     * @var StatusMapper
     */
    protected $statusMapper;

    /**
     * Create a gateway with configuration and an optional shared status mapper.
     *
     * @param array $config Gateway-specific configuration.
     * @param StatusMapper|null $statusMapper Optional status mapper.
     * @return void
     */
    public function __construct(array $config = array(), StatusMapper $statusMapper = null)
    {
        $this->config = $config;
        $this->statusMapper = $statusMapper ?: new StatusMapper();
    }

    /**
     * Return gateway status for an order when implemented by a concrete driver.
     *
     * @param string $orderId Application order identifier.
     * @return PaymentResponse|array|null Gateway-specific status response.
     */
    public function getStatus($orderId)
    {
        return null;
    }

    /**
     * Verify and normalize a callback payload.
     *
     * @param array $payload Raw callback payload.
     * @return CallbackResponse Normalized callback response.
     */
    public function handleCallback(array $payload)
    {
        // Invalid callbacks are returned as data so the manager can raise the package exception.
        if (! $this->verifyCallback($payload)) {
            return new CallbackResponse(array(
                'valid' => false,
                'gateway' => $this->getName(),
                'raw' => $payload,
            ));
        }

        $rawStatus = $this->payloadValue($payload, array('transaction_status', 'status', 'resultCode'));
        $orderId = $this->payloadValue($payload, array('order_id', 'merchantOrderId', 'external_id', 'invoice_number'));

        return new CallbackResponse(array(
            'valid' => true,
            'gateway' => $this->getName(),
            'order_id' => $orderId,
            'status' => $this->statusMapper->map($this->getName(), $rawStatus),
            'transaction_id' => $this->payloadValue($payload, array('transaction_id', 'id')),
            'gateway_order_id' => $this->payloadValue($payload, array('gateway_order_id', 'reference', 'invoice_id')),
            'raw' => $payload,
        ));
    }

    /**
     * Verify callback authenticity; concrete gateways should override when possible.
     *
     * @param array $payload Raw callback payload.
     * @return bool True when callback is accepted.
     */
    public function verifyCallback(array $payload)
    {
        return true;
    }

    /**
     * Report gateway availability before payment creation.
     *
     * @return GatewayHealthResponse Health check result.
     */
    public function healthCheck()
    {
        return new GatewayHealthResponse(true, $this->getName());
    }

    /**
     * Find the first available payload value from a list of gateway-specific keys.
     *
     * @param array $payload Raw gateway payload.
     * @param array $keys Candidate field names.
     * @return string|int|float|bool|array|null First matching payload value.
     */
    protected function payloadValue(array $payload, array $keys)
    {
        // Gateway payloads use different field names for the same concept.
        foreach ($keys as $key) {
            if (isset($payload[$key])) {
                return $payload[$key];
            }
        }

        return null;
    }
}
