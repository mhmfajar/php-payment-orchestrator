<?php

namespace Mhmfajar\PaymentOrchestrator\Gateways;

use Mhmfajar\PaymentOrchestrator\Constants\GatewayFailureReason;
use Mhmfajar\PaymentOrchestrator\Constants\PaymentStatus;
use Mhmfajar\PaymentOrchestrator\DTO\CallbackResponse;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;

/**
 * Duitku checkout/inquiry gateway driver.
 */
class DuitkuGateway extends AbstractGateway
{
    /**
     * Create a Duitku transaction inquiry.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @return PaymentResponse Normalized payment response.
     */
    public function createPayment(PaymentRequest $request)
    {
        $missing = array();
        foreach (array('merchant_code', 'api_key') as $key) {
            if (! $this->hasConfigValue($key)) {
                $missing[] = $key;
            }
        }

        if (count($missing) > 0) {
            return $this->missingConfigResponse($request, $missing);
        }

        $merchantCode = $this->configValue('merchant_code');
        $amount = $request->getAmount();
        $orderId = $request->getOrderId();
        $payload = array(
            'merchantCode' => $merchantCode,
            'paymentAmount' => $amount,
            'merchantOrderId' => $orderId,
            'productDetails' => $request->getDescription() ?: 'Payment ' . $orderId,
            'customerVaName' => $request->getCustomerName(),
            'email' => $request->getCustomerEmail(),
            'itemDetails' => $this->lineItems($request),
            'customerDetail' => array(
                'firstName' => $request->getCustomerName(),
                'email' => $request->getCustomerEmail(),
            ),
            'callbackUrl' => $request->getCallbackUrl() ?: $this->configValue('callback_url'),
            'returnUrl' => $request->getReturnUrl() ?: $this->configValue('return_url'),
            'signature' => hash_hmac('sha256', $merchantCode . $orderId . $amount, $this->configValue('api_key')),
        );

        if ($this->hasConfigValue('payment_method')) {
            $payload['paymentMethod'] = $this->configValue('payment_method');
        }

        if ($request->getCustomerPhone()) {
            $payload['phoneNumber'] = $request->getCustomerPhone();
            $payload['customerDetail']['phoneNumber'] = $request->getCustomerPhone();
        }

        if ($this->hasConfigValue('expiry_period')) {
            $payload['expiryPeriod'] = (int) $this->configValue('expiry_period');
        }

        $response = $this->requestJson('POST', $this->baseUrl() . '/webapi/api/merchant/v2/inquiry', $payload);

        if (! $this->httpOk($response)) {
            return $this->failedHttpResponse($request, $response);
        }

        $body = $response['json'];
        $paymentUrl = $this->payloadValue($body, array('paymentUrl', 'payment_url', 'AppUrl', 'appUrl'));
        $reference = $this->payloadValue($body, array('reference'));
        $statusCode = $this->payloadValue($body, array('statusCode', 'resultCode'));

        if (! $paymentUrl && ! $reference && ! $this->payloadValue($body, array('vaNumber', 'qrString'))) {
            return new PaymentResponse(array(
                'success' => false,
                'gateway' => $this->getName(),
                'order_id' => $orderId,
                'message' => 'Duitku response did not include payment instructions.',
                'failure_reason' => GatewayFailureReason::INVALID_GATEWAY_RESPONSE,
                'fallback_allowed' => true,
                'raw' => $response,
            ));
        }

        return new PaymentResponse(array(
            'success' => true,
            'gateway' => $this->getName(),
            'order_id' => $orderId,
            'status' => $statusCode ? $this->statusMapper->map($this->getName(), $statusCode) : PaymentStatus::PENDING,
            'gateway_order_id' => $reference,
            'payment_url' => $paymentUrl,
            'qr_string' => $this->payloadValue($body, array('qrString')),
            'va_number' => $this->payloadValue($body, array('vaNumber')),
            'message' => $this->payloadValue($body, array('statusMessage', 'responseMessage')) ?: 'Duitku transaction created.',
            'raw' => $body,
        ));
    }

    /**
     * Retrieve a Duitku transaction status.
     *
     * @param string $orderId Application order identifier.
     * @return PaymentResponse
     */
    public function getStatus($orderId)
    {
        $merchantCode = $this->configValue('merchant_code');
        $payload = array(
            'merchantCode' => $merchantCode,
            'merchantOrderId' => $orderId,
            'signature' => hash_hmac('sha256', $merchantCode . $orderId, $this->configValue('api_key')),
        );

        $response = $this->requestJson('POST', $this->baseUrl() . '/webapi/api/merchant/transactionStatus', $payload);

        if (! $this->httpOk($response)) {
            return $this->failedHttpResponse($orderId, $response);
        }

        $body = $response['json'];
        $statusCode = $this->payloadValue($body, array('statusCode', 'resultCode'));

        return new PaymentResponse(array(
            'success' => true,
            'gateway' => $this->getName(),
            'order_id' => $orderId,
            'status' => $this->statusMapper->map($this->getName(), $statusCode),
            'gateway_order_id' => $this->payloadValue($body, array('reference')),
            'message' => $this->payloadValue($body, array('statusMessage', 'responseMessage')),
            'raw' => $body,
        ));
    }

    /**
     * Normalize a Duitku callback payload.
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
            'order_id' => $this->payloadValue($payload, array('merchantOrderId')),
            'status' => $this->statusMapper->map($this->getName(), $this->payloadValue($payload, array('resultCode'))),
            'gateway_order_id' => $this->payloadValue($payload, array('reference', 'publisherOrderId')),
            'raw' => $payload,
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

        $signature = hash_hmac('sha256', $payload['merchantCode'] . $payload['amount'] . $payload['merchantOrderId'], $this->config['api_key']);
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

    /**
     * Return the Duitku API base URL.
     *
     * @return string
     */
    private function baseUrl()
    {
        if ($this->configValue('base_url')) {
            return rtrim($this->configValue('base_url'), '/');
        }

        return $this->configValue('is_production') ? 'https://passport.duitku.com' : 'https://sandbox.duitku.com';
    }
}
