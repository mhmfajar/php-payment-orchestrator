<?php

namespace Mhmfajar\PaymentOrchestrator\Gateways;

use Mhmfajar\PaymentOrchestrator\Constants\GatewayFailureReason;
use Mhmfajar\PaymentOrchestrator\Constants\PaymentStatus;
use Mhmfajar\PaymentOrchestrator\DTO\CallbackResponse;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;

/**
 * Xendit invoice gateway driver.
 */
class XenditGateway extends AbstractGateway
{
    /**
     * Create a Xendit invoice.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @return PaymentResponse Normalized payment response.
     */
    public function createPayment(PaymentRequest $request)
    {
        if (! $this->hasConfigValue('secret_key')) {
            return $this->missingConfigResponse($request, array('secret_key'));
        }

        $payload = array(
            'external_id' => $request->getOrderId(),
            'amount' => $request->getAmount(),
            'payer_email' => $request->getCustomerEmail(),
            'description' => $request->getDescription() ?: 'Payment ' . $request->getOrderId(),
            'currency' => $request->getCurrency(),
            'items' => $this->lineItems($request),
        );

        if ($request->getReturnUrl()) {
            $payload['success_redirect_url'] = $request->getReturnUrl();
            $payload['failure_redirect_url'] = $request->getReturnUrl();
        }

        if ($request->getCallbackUrl() || $this->hasConfigValue('callback_url')) {
            $payload['callback_url'] = $request->getCallbackUrl() ?: $this->configValue('callback_url');
        }

        if ($this->hasConfigValue('invoice_duration')) {
            $payload['invoice_duration'] = (int) $this->configValue('invoice_duration');
        }

        $response = $this->requestJson('POST', $this->baseUrl() . '/v2/invoices', $payload, array(
            'Authorization: Basic ' . base64_encode($this->configValue('secret_key') . ':'),
        ));

        if (! $this->httpOk($response)) {
            return $this->failedHttpResponse($request, $response);
        }

        $body = $response['json'];
        $invoiceUrl = $this->payloadValue($body, array('invoice_url'));
        $invoiceId = $this->payloadValue($body, array('id'));

        if (! $invoiceUrl || ! $invoiceId) {
            return new PaymentResponse(array(
                'success' => false,
                'gateway' => $this->getName(),
                'order_id' => $request->getOrderId(),
                'message' => 'Xendit response did not include an invoice URL.',
                'failure_reason' => GatewayFailureReason::INVALID_GATEWAY_RESPONSE,
                'fallback_allowed' => true,
                'raw' => $response,
            ));
        }

        return new PaymentResponse(array(
            'success' => true,
            'gateway' => $this->getName(),
            'order_id' => $request->getOrderId(),
            'status' => $this->statusMapper->map($this->getName(), $this->payloadValue($body, array('status')) ?: 'PENDING'),
            'transaction_id' => $invoiceId,
            'gateway_order_id' => $invoiceId,
            'payment_url' => $invoiceUrl,
            'message' => 'Xendit invoice created.',
            'raw' => $body,
        ));
    }

    /**
     * Retrieve a Xendit invoice status.
     *
     * @param string $orderId Xendit invoice id or external id.
     * @return PaymentResponse
     */
    public function getStatus($orderId)
    {
        $response = $this->requestJson('GET', $this->baseUrl() . '/v2/invoices/' . rawurlencode($orderId), null, array(
            'Authorization: Basic ' . base64_encode($this->configValue('secret_key') . ':'),
        ));

        if (! $this->httpOk($response)) {
            return $this->failedHttpResponse($orderId, $response);
        }

        $body = $response['json'];

        return new PaymentResponse(array(
            'success' => true,
            'gateway' => $this->getName(),
            'order_id' => $this->payloadValue($body, array('external_id')) ?: $orderId,
            'status' => $this->statusMapper->map($this->getName(), $this->payloadValue($body, array('status'))),
            'transaction_id' => $this->payloadValue($body, array('id')),
            'gateway_order_id' => $this->payloadValue($body, array('id')),
            'payment_url' => $this->payloadValue($body, array('invoice_url')),
            'raw' => $body,
        ));
    }

    /**
     * Normalize a Xendit invoice callback payload.
     *
     * @param array $payload Raw callback payload.
     * @return CallbackResponse
     */
    public function handleCallback(array $payload)
    {
        if (! $this->verifyCallback($payload)) {
            return new CallbackResponse(array('valid' => false, 'gateway' => $this->getName(), 'raw' => $payload));
        }

        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;

        return new CallbackResponse(array(
            'valid' => true,
            'gateway' => $this->getName(),
            'order_id' => $this->payloadValue($data, array('external_id')),
            'status' => $this->statusMapper->map($this->getName(), $this->payloadValue($data, array('status', 'payment_status'))),
            'transaction_id' => $this->payloadValue($data, array('id')),
            'gateway_order_id' => $this->payloadValue($data, array('id')),
            'raw' => $payload,
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
        if (! isset($this->config['callback_token']) || $this->config['callback_token'] === '') {
            return parent::verifyCallback($payload);
        }

        $headers = isset($payload['_headers']) && is_array($payload['_headers']) ? $payload['_headers'] : array();
        $token = $this->payloadValue($payload, array('x-callback-token', 'callback_token', 'callback_authentication_token'));

        if ($token === null) {
            $token = $this->headerValue($headers, 'x-callback-token');
        }

        return $token !== null && hash_equals($this->config['callback_token'], $token);
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

    /**
     * Return the Xendit API base URL.
     *
     * @return string
     */
    private function baseUrl()
    {
        return rtrim($this->configValue('base_url', 'https://api.xendit.co'), '/');
    }

    /**
     * Return a case-insensitive header value from a callback payload header bag.
     *
     * @param array $headers Callback headers.
     * @param string $name Header name.
     * @return string|null
     */
    private function headerValue(array $headers, $name)
    {
        foreach ($headers as $key => $value) {
            if (strtolower($key) === strtolower($name)) {
                return is_array($value) ? reset($value) : $value;
            }
        }

        return null;
    }
}
