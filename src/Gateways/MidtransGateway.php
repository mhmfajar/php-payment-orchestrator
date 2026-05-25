<?php

namespace Mhmfajar\PaymentOrchestrator\Gateways;

use Mhmfajar\PaymentOrchestrator\Constants\GatewayFailureReason;
use Mhmfajar\PaymentOrchestrator\Constants\PaymentStatus;
use Mhmfajar\PaymentOrchestrator\DTO\CallbackResponse;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;

/**
 * Midtrans Snap gateway driver.
 */
class MidtransGateway extends AbstractGateway
{
    /**
     * Create a Midtrans Snap transaction.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @return PaymentResponse Normalized payment response.
     */
    public function createPayment(PaymentRequest $request)
    {
        if (! $this->hasConfigValue('server_key')) {
            return $this->missingConfigResponse($request, array('server_key'));
        }

        $payload = array(
            'transaction_details' => array(
                'order_id' => $request->getOrderId(),
                'gross_amount' => $request->getAmount(),
            ),
            'customer_details' => array(
                'first_name' => $request->getCustomerName(),
                'email' => $request->getCustomerEmail(),
            ),
            'item_details' => $this->lineItems($request),
        );

        if ($request->getCustomerPhone()) {
            $payload['customer_details']['phone'] = $request->getCustomerPhone();
        }

        if ($request->getReturnUrl()) {
            $payload['callbacks'] = array('finish' => $request->getReturnUrl());
        }

        if ($request->getCallbackUrl() || $this->hasConfigValue('callback_url')) {
            $payload['notification_url'] = $request->getCallbackUrl() ?: $this->configValue('callback_url');
        }

        $response = $this->requestJson('POST', $this->baseUrl() . '/snap/v1/transactions', $payload, array(
            'Authorization: Basic ' . base64_encode($this->configValue('server_key') . ':'),
        ));

        if (! $this->httpOk($response)) {
            return $this->failedHttpResponse($request, $response);
        }

        $body = $response['json'];
        $token = $this->payloadValue($body, array('token'));
        $redirectUrl = $this->payloadValue($body, array('redirect_url'));

        if (! $token || ! $redirectUrl) {
            return new PaymentResponse(array(
                'success' => false,
                'gateway' => $this->getName(),
                'order_id' => $request->getOrderId(),
                'message' => 'Midtrans response did not include a Snap token and redirect URL.',
                'failure_reason' => GatewayFailureReason::INVALID_GATEWAY_RESPONSE,
                'fallback_allowed' => true,
                'raw' => $response,
            ));
        }

        return new PaymentResponse(array(
            'success' => true,
            'gateway' => $this->getName(),
            'order_id' => $request->getOrderId(),
            'status' => PaymentStatus::PENDING,
            'transaction_id' => $token,
            'gateway_order_id' => $request->getOrderId(),
            'payment_url' => $redirectUrl,
            'message' => 'Midtrans Snap transaction created.',
            'raw' => $body,
        ));
    }

    /**
     * Retrieve a Midtrans transaction status.
     *
     * @param string $orderId Application order identifier.
     * @return PaymentResponse
     */
    public function getStatus($orderId)
    {
        $response = $this->requestJson('GET', $this->baseUrl() . '/v2/' . rawurlencode($orderId) . '/status', null, array(
            'Authorization: Basic ' . base64_encode($this->configValue('server_key') . ':'),
        ));

        if (! $this->httpOk($response)) {
            return $this->failedHttpResponse($orderId, $response);
        }

        $body = $response['json'];
        $rawStatus = $this->payloadValue($body, array('transaction_status'));

        return new PaymentResponse(array(
            'success' => true,
            'gateway' => $this->getName(),
            'order_id' => $orderId,
            'status' => $this->statusMapper->map($this->getName(), $rawStatus),
            'transaction_id' => $this->payloadValue($body, array('transaction_id')),
            'gateway_order_id' => $this->payloadValue($body, array('order_id')),
            'raw' => $body,
        ));
    }

    /**
     * Normalize a Midtrans callback payload.
     *
     * @param array $payload Raw callback payload.
     * @return CallbackResponse
     */
    public function handleCallback(array $payload)
    {
        if (! $this->verifyCallback($payload)) {
            return new CallbackResponse(array('valid' => false, 'gateway' => $this->getName(), 'raw' => $payload));
        }

        return new CallbackResponse(array(
            'valid' => true,
            'gateway' => $this->getName(),
            'order_id' => $this->payloadValue($payload, array('order_id')),
            'status' => $this->statusMapper->map($this->getName(), $this->payloadValue($payload, array('transaction_status'))),
            'transaction_id' => $this->payloadValue($payload, array('transaction_id')),
            'gateway_order_id' => $this->payloadValue($payload, array('order_id')),
            'raw' => $payload,
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

    /**
     * Return the Midtrans API base URL.
     *
     * @return string
     */
    private function baseUrl()
    {
        if ($this->configValue('base_url')) {
            return rtrim($this->configValue('base_url'), '/');
        }

        return $this->configValue('is_production') ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
    }
}
