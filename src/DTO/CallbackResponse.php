<?php

namespace Mhmfajar\PaymentOrchestrator\DTO;

/**
 * Represents a verified and normalized gateway callback result.
 */
class CallbackResponse
{
    /**
     * Whether the callback passed gateway verification.
     *
     * @var bool
     */
    private $valid;

    /**
     * Gateway that sent the callback.
     *
     * @var string
     */
    private $gateway;

    /**
     * Application order identifier.
     *
     * @var string
     */
    private $orderId;

    /**
     * Universal status parsed from callback payload.
     *
     * @var string
     */
    private $status;

    /**
     * Gateway transaction identifier.
     *
     * @var string|null
     */
    private $transactionId;

    /**
     * Gateway-side order identifier.
     *
     * @var string|null
     */
    private $gatewayOrderId;

    /**
     * Raw callback payload.
     *
     * @var array
     */
    private $raw;

    /**
     * Create a normalized callback response from gateway callback data.
     *
     * @param array $data Callback response data.
     * @return void
     */
    public function __construct(array $data)
    {
        $this->valid = isset($data['valid']) ? (bool) $data['valid'] : false;
        $this->gateway = isset($data['gateway']) ? $data['gateway'] : '';
        $this->orderId = isset($data['order_id']) ? $data['order_id'] : '';
        $this->status = isset($data['status']) ? $data['status'] : '';
        $this->transactionId = isset($data['transaction_id']) ? $data['transaction_id'] : null;
        $this->gatewayOrderId = isset($data['gateway_order_id']) ? $data['gateway_order_id'] : null;
        $this->raw = isset($data['raw']) && is_array($data['raw']) ? $data['raw'] : array();
    }

    /**
     * Return whether callback verification passed.
     *
     * @return bool
     */
    public function isValid() { return $this->valid; }

    /**
     * Return the callback gateway name.
     *
     * @return string
     */
    public function getGateway() { return $this->gateway; }

    /**
     * Return the application order identifier.
     *
     * @return string
     */
    public function getOrderId() { return $this->orderId; }

    /**
     * Return the mapped universal status.
     *
     * @return string
     */
    public function getStatus() { return $this->status; }

    /**
     * Return the gateway transaction identifier.
     *
     * @return string|null
     */
    public function getTransactionId() { return $this->transactionId; }

    /**
     * Return the gateway-side order identifier.
     *
     * @return string|null
     */
    public function getGatewayOrderId() { return $this->gatewayOrderId; }

    /**
     * Return the raw callback payload.
     *
     * @return array
     */
    public function getRaw() { return $this->raw; }
}
