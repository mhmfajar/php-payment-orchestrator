<?php

namespace Mhmfajar\PaymentOrchestrator\Tests\Fakes;

use Mhmfajar\PaymentOrchestrator\DTO\CallbackResponse;
use Mhmfajar\PaymentOrchestrator\DTO\GatewayHealthResponse;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;
use Mhmfajar\PaymentOrchestrator\Gateways\AbstractGateway;

/**
 * Configurable fake gateway used to exercise orchestration paths without external APIs.
 */
class FakeGateway extends AbstractGateway
{
    /**
     * Fake gateway name returned to the orchestrator.
     *
     * @var string
     */
    private $name;

    /**
     * Optional fixed response returned by createPayment.
     *
     * @var PaymentResponse|null
     */
    private $response;

    /**
     * Fake health check availability.
     *
     * @var bool
     */
    private $available;

    /**
     * Fake callback verification result.
     *
     * @var bool
     */
    private $callbackValid;

    /**
     * Create a fake gateway with configurable behavior.
     *
     * @param string $name Fake gateway name.
     * @param PaymentResponse|null $response Optional fixed payment response.
     * @param bool $available Fake health check availability.
     * @param bool $callbackValid Fake callback verification result.
     * @return void
     */
    public function __construct($name, PaymentResponse $response = null, $available = true, $callbackValid = true)
    {
        parent::__construct(array());
        $this->name = $name;
        $this->response = $response;
        $this->available = $available;
        $this->callbackValid = $callbackValid;
    }

    /**
     * Return the configured response or a default successful payment URL.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @return PaymentResponse Payment response.
     */
    public function createPayment(PaymentRequest $request)
    {
        if ($this->response) {
            return $this->response;
        }

        return new PaymentResponse(array(
            'success' => true,
            'gateway' => $this->name,
            'order_id' => $request->getOrderId(),
            'status' => 'pending',
            'payment_url' => 'https://pay.example/' . $request->getOrderId(),
        ));
    }

    /**
     * Return fake gateway availability.
     *
     * @return GatewayHealthResponse Health check response.
     */
    public function healthCheck()
    {
        return new GatewayHealthResponse($this->available, $this->name);
    }

    /**
     * Return the configured callback validity.
     *
     * @param array $payload Raw callback payload.
     * @return bool Callback validity.
     */
    public function verifyCallback(array $payload)
    {
        return $this->callbackValid;
    }

    /**
     * Return an invalid callback response when callback validation is configured to fail.
     *
     * @param array $payload Raw callback payload.
     * @return CallbackResponse Normalized callback response.
     */
    public function handleCallback(array $payload)
    {
        if (! $this->callbackValid) {
            return new CallbackResponse(array('valid' => false, 'gateway' => $this->name, 'raw' => $payload));
        }

        return parent::handleCallback($payload);
    }

    /**
     * Return the fake gateway name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
