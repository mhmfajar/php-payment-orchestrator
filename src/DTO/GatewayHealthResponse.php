<?php

namespace Mhmfajar\PaymentOrchestrator\DTO;

/**
 * Reports whether a gateway is available before attempting payment creation.
 */
class GatewayHealthResponse
{
    /**
     * Whether the gateway is available for payment creation.
     *
     * @var bool
     */
    private $available;

    /**
     * Gateway name checked.
     *
     * @var string
     */
    private $gateway;

    /**
     * Health check detail message.
     *
     * @var string|null
     */
    private $message;

    /**
     * Raw health check context.
     *
     * @var array
     */
    private $raw;

    /**
     * Create a normalized gateway health result.
     *
     * @param bool $available Whether the gateway is available.
     * @param string $gateway Gateway name.
     * @param string|null $message Optional health detail message.
     * @param array $raw Raw health check context.
     * @return void
     */
    public function __construct($available, $gateway, $message = null, array $raw = array())
    {
        $this->available = (bool) $available;
        $this->gateway = $gateway;
        $this->message = $message;
        $this->raw = $raw;
    }

    /**
     * Return whether the gateway is available.
     *
     * @return bool
     */
    public function isAvailable() { return $this->available; }

    /**
     * Return the gateway name.
     *
     * @return string
     */
    public function getGateway() { return $this->gateway; }

    /**
     * Return the health check message.
     *
     * @return string|null
     */
    public function getMessage() { return $this->message; }

    /**
     * Return raw health check context.
     *
     * @return array
     */
    public function getRaw() { return $this->raw; }
}
