<?php

namespace Mhmfajar\PaymentOrchestrator\Gateways;

use Mhmfajar\PaymentOrchestrator\Constants\GatewayFailureReason;
use Mhmfajar\PaymentOrchestrator\Constants\PaymentStatus;
use Mhmfajar\PaymentOrchestrator\DTO\CallbackResponse;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;

/**
 * DOKU Checkout gateway driver.
 */
class DokuGateway extends AbstractGateway
{
    /**
     * Create a DOKU Checkout payment.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @return PaymentResponse Normalized payment response.
     */
    public function createPayment(PaymentRequest $request)
    {
        $missing = array();
        foreach (array('client_id', 'secret_key') as $key) {
            if (! $this->hasConfigValue($key)) {
                $missing[] = $key;
            }
        }

        if (count($missing) > 0) {
            return $this->missingConfigResponse($request, $missing);
        }

        $payload = array(
            'order' => array(
                'amount' => $request->getAmount(),
                'invoice_number' => $request->getOrderId(),
                'currency' => $request->getCurrency(),
                'line_items' => $this->lineItems($request),
            ),
            'payment' => array(),
            'customer' => array(
                'name' => $request->getCustomerName(),
                'email' => $request->getCustomerEmail(),
            ),
        );

        if ($request->getReturnUrl()) {
            $payload['order']['callback_url'] = $request->getReturnUrl();
            $payload['order']['callback_url_result'] = $request->getReturnUrl();
        }

        if ($request->getCallbackUrl() || $this->hasConfigValue('callback_url')) {
            $payload['additional_info'] = array(
                'override_notification_url' => $request->getCallbackUrl() ?: $this->configValue('callback_url'),
            );
        }

        if ($request->getCustomerPhone()) {
            $payload['customer']['phone'] = $request->getCustomerPhone();
        }

        if ($this->hasConfigValue('payment_due_date')) {
            $payload['payment']['payment_due_date'] = (int) $this->configValue('payment_due_date');
        }

        if ($this->hasConfigValue('payment_method_types')) {
            $payload['payment']['payment_method_types'] = (array) $this->configValue('payment_method_types');
        }

        $target = '/checkout/v1/payment';
        $body = json_encode($payload);
        $response = $this->httpRequest('POST', $this->baseUrl() . $target, $body, $this->signatureHeaders($target, $body));

        if (! $this->httpOk($response)) {
            return $this->failedHttpResponse($request, $response);
        }

        $responseBody = $response['json'];
        $payment = isset($responseBody['response']['payment']) && is_array($responseBody['response']['payment'])
            ? $responseBody['response']['payment']
            : (isset($responseBody['payment']) && is_array($responseBody['payment']) ? $responseBody['payment'] : array());
        $order = isset($responseBody['response']['order']) && is_array($responseBody['response']['order'])
            ? $responseBody['response']['order']
            : (isset($responseBody['order']) && is_array($responseBody['order']) ? $responseBody['order'] : array());
        $paymentUrl = $this->payloadValue($payment, array('url', 'payment_url'));

        if (! $paymentUrl) {
            return new PaymentResponse(array(
                'success' => false,
                'gateway' => $this->getName(),
                'order_id' => $request->getOrderId(),
                'message' => 'DOKU response did not include payment.url.',
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
            'gateway_order_id' => $this->payloadValue($order, array('invoice_number')) ?: $request->getOrderId(),
            'payment_url' => $paymentUrl,
            'message' => 'DOKU Checkout payment created.',
            'raw' => $responseBody,
        ));
    }

    /**
     * Retrieve DOKU order status for Checkout.
     *
     * @param string $orderId Application order identifier.
     * @return PaymentResponse
     */
    public function getStatus($orderId)
    {
        $target = '/orders/v1/status/' . rawurlencode($orderId);
        $response = $this->httpRequest('GET', $this->baseUrl() . $target, null, $this->signatureHeaders($target, ''));

        if (! $this->httpOk($response)) {
            return $this->failedHttpResponse($orderId, $response);
        }

        $body = $response['json'];
        $order = isset($body['order']) && is_array($body['order']) ? $body['order'] : $body;
        $transaction = isset($body['transaction']) && is_array($body['transaction']) ? $body['transaction'] : $body;

        return new PaymentResponse(array(
            'success' => true,
            'gateway' => $this->getName(),
            'order_id' => $this->payloadValue($order, array('invoice_number')) ?: $orderId,
            'status' => $this->statusMapper->map($this->getName(), $this->payloadValue($transaction, array('status', 'status_code'))),
            'transaction_id' => $this->payloadValue($transaction, array('id', 'transaction_id')),
            'gateway_order_id' => $this->payloadValue($order, array('invoice_number')),
            'raw' => $body,
        ));
    }

    /**
     * Normalize a DOKU notification payload.
     *
     * @param array $payload Raw callback payload.
     * @return CallbackResponse
     */
    public function handleCallback(array $payload)
    {
        if (! $this->verifyCallback($payload)) {
            return new CallbackResponse(array('valid' => false, 'gateway' => $this->getName(), 'raw' => $payload));
        }

        $order = isset($payload['order']) && is_array($payload['order']) ? $payload['order'] : $payload;
        $transaction = isset($payload['transaction']) && is_array($payload['transaction']) ? $payload['transaction'] : $payload;

        return new CallbackResponse(array(
            'valid' => true,
            'gateway' => $this->getName(),
            'order_id' => $this->payloadValue($order, array('invoice_number')),
            'status' => $this->statusMapper->map($this->getName(), $this->payloadValue($transaction, array('status', 'status_code'))),
            'transaction_id' => $this->payloadValue($transaction, array('id', 'transaction_id')),
            'gateway_order_id' => $this->payloadValue($order, array('invoice_number')),
            'raw' => $payload,
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
        if (! $this->hasConfigValue('secret_key')) {
            return parent::verifyCallback($payload);
        }

        if (! isset($payload['_headers']) || ! is_array($payload['_headers'])) {
            return parent::verifyCallback($payload);
        }

        $headers = $payload['_headers'];
        $signature = $this->headerValue($headers, 'Signature');
        $clientId = $this->headerValue($headers, 'Client-Id');
        $requestId = $this->headerValue($headers, 'Request-Id');
        $timestamp = $this->headerValue($headers, 'Request-Timestamp');
        $target = $this->headerValue($headers, 'Request-Target') ?: $this->configValue('callback_request_target', '/checkout/v1/payment/notification');

        if (! $signature || ! $clientId || ! $requestId || ! $timestamp) {
            return false;
        }

        $copy = $payload;
        unset($copy['_headers']);
        $body = json_encode($copy);
        $expected = $this->makeSignature($clientId, $requestId, $timestamp, $target, $body);

        return hash_equals($expected, $signature);
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

    /**
     * Return the DOKU API base URL.
     *
     * @return string
     */
    private function baseUrl()
    {
        if ($this->configValue('base_url')) {
            return rtrim($this->configValue('base_url'), '/');
        }

        return $this->configValue('is_production') ? 'https://api.doku.com' : 'https://api-sandbox.doku.com';
    }

    /**
     * Build DOKU signature headers for a request.
     *
     * @param string $target Request target path.
     * @param string $body Request body.
     * @return array
     */
    private function signatureHeaders($target, $body)
    {
        $clientId = $this->configValue('client_id');
        $requestId = $this->requestId();
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return array(
            'Content-Type: application/json',
            'Client-Id: ' . $clientId,
            'Request-Id: ' . $requestId,
            'Request-Timestamp: ' . $timestamp,
            'Signature: ' . $this->makeSignature($clientId, $requestId, $timestamp, $target, $body),
        );
    }

    /**
     * Create a DOKU HMACSHA256 signature header value.
     *
     * @param string $clientId DOKU client id.
     * @param string $requestId Unique request id.
     * @param string $timestamp UTC request timestamp.
     * @param string $target Request target path.
     * @param string $body Request body.
     * @return string
     */
    private function makeSignature($clientId, $requestId, $timestamp, $target, $body)
    {
        $digest = base64_encode(hash('sha256', $body, true));
        $component = 'Client-Id:' . $clientId . "\n"
            . 'Request-Id:' . $requestId . "\n"
            . 'Request-Timestamp:' . $timestamp . "\n"
            . 'Request-Target:' . $target . "\n"
            . 'Digest:' . $digest;

        return 'HMACSHA256=' . base64_encode(hash_hmac('sha256', $component, $this->configValue('secret_key'), true));
    }

    /**
     * Return a request id for DOKU duplicate protection.
     *
     * @return string
     */
    private function requestId()
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(16));
        }

        return str_replace('.', '', uniqid('', true));
    }

    /**
     * Return a case-insensitive header value.
     *
     * @param array $headers Header bag.
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
