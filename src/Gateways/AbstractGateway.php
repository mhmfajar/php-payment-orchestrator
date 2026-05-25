<?php

namespace Mhmfajar\PaymentOrchestrator\Gateways;

use Mhmfajar\PaymentOrchestrator\Contracts\PaymentGatewayInterface;
use Mhmfajar\PaymentOrchestrator\DTO\CallbackResponse;
use Mhmfajar\PaymentOrchestrator\DTO\GatewayHealthResponse;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;
use Mhmfajar\PaymentOrchestrator\Constants\GatewayFailureReason;
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

    /**
     * Return a gateway configuration value with a default.
     *
     * @param string $key Configuration key.
     * @param mixed $default Default value.
     * @return mixed
     */
    protected function configValue($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    /**
     * Check whether a configuration value is present.
     *
     * @param string $key Configuration key.
     * @return bool
     */
    protected function hasConfigValue($key)
    {
        return isset($this->config[$key]) && $this->config[$key] !== null && $this->config[$key] !== '';
    }

    /**
     * Build a missing configuration payment response.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @param array $keys Missing configuration keys.
     * @return PaymentResponse
     */
    protected function missingConfigResponse(PaymentRequest $request, array $keys)
    {
        return new PaymentResponse(array(
            'success' => false,
            'gateway' => $this->getName(),
            'order_id' => $request->getOrderId(),
            'message' => 'Missing gateway configuration: ' . implode(', ', $keys) . '.',
            'failure_reason' => GatewayFailureReason::VALIDATION_ERROR,
            'fallback_allowed' => false,
            'raw' => array('missing_config' => $keys),
        ));
    }

    /**
     * Build a normalized response from an unsuccessful HTTP call.
     *
     * @param PaymentRequest|string $request Payment request or order id.
     * @param array $httpResponse HTTP response context.
     * @return PaymentResponse
     */
    protected function failedHttpResponse($request, array $httpResponse)
    {
        $statusCode = isset($httpResponse['status_code']) ? (int) $httpResponse['status_code'] : 0;
        $body = isset($httpResponse['json']) && is_array($httpResponse['json']) ? $httpResponse['json'] : array();
        $message = $this->payloadValue($body, array('message', 'Message', 'statusMessage', 'error_message', 'error', 'response_message'));

        if ($message === null && isset($httpResponse['error'])) {
            $message = $httpResponse['error'];
        }

        if ($message === null) {
            $message = 'Gateway HTTP request failed.';
        }

        $failureReason = GatewayFailureReason::UNKNOWN;
        $fallbackAllowed = false;

        if ($statusCode === 0 || $statusCode === 408) {
            $failureReason = GatewayFailureReason::CONNECTION_TIMEOUT;
            $fallbackAllowed = true;
        } elseif ($statusCode === 502 || $statusCode === 503 || $statusCode === 504) {
            $failureReason = GatewayFailureReason::GATEWAY_UNAVAILABLE;
            $fallbackAllowed = true;
        } elseif ($statusCode >= 500) {
            $failureReason = GatewayFailureReason::SERVER_ERROR;
            $fallbackAllowed = true;
        } elseif ($statusCode >= 400) {
            $failureReason = GatewayFailureReason::VALIDATION_ERROR;
        }

        $orderId = $request instanceof PaymentRequest ? $request->getOrderId() : $request;

        return new PaymentResponse(array(
            'success' => false,
            'gateway' => $this->getName(),
            'order_id' => $orderId,
            'message' => $message,
            'failure_reason' => $failureReason,
            'fallback_allowed' => $fallbackAllowed,
            'raw' => $httpResponse,
        ));
    }

    /**
     * Send a JSON HTTP request and decode a JSON response.
     *
     * @param string $method HTTP method.
     * @param string $url Target URL.
     * @param array|null $payload Optional JSON payload.
     * @param array $headers HTTP headers.
     * @return array Normalized HTTP response.
     */
    protected function requestJson($method, $url, array $payload = null, array $headers = array())
    {
        $body = $payload === null ? null : json_encode($payload);
        $headers[] = 'Accept: application/json';

        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        return $this->httpRequest($method, $url, $body, $headers);
    }

    /**
     * Send a form-encoded HTTP request and decode a JSON response.
     *
     * @param string $method HTTP method.
     * @param string $url Target URL.
     * @param array $payload Form payload.
     * @param array $headers HTTP headers.
     * @return array Normalized HTTP response.
     */
    protected function requestForm($method, $url, array $payload, array $headers = array())
    {
        $headers[] = 'Accept: application/json';
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';

        return $this->httpRequest($method, $url, http_build_query($payload), $headers);
    }

    /**
     * Send an HTTP request using an injected client or PHP streams.
     *
     * @param string $method HTTP method.
     * @param string $url Target URL.
     * @param string|null $body Request body.
     * @param array $headers HTTP headers.
     * @return array Normalized HTTP response.
     */
    protected function httpRequest($method, $url, $body = null, array $headers = array())
    {
        if (isset($this->config['http_client']) && is_callable($this->config['http_client'])) {
            $response = call_user_func($this->config['http_client'], $method, $url, $body, $headers, $this->config);
            return $this->normalizeHttpResponse($response);
        }

        $options = array(
            'http' => array(
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => (int) $this->configValue('timeout', 30),
            ),
        );

        if ($body !== null) {
            $options['http']['content'] = $body;
        }

        $rawBody = @file_get_contents($url, false, stream_context_create($options));
        $responseHeaders = isset($http_response_header) ? $http_response_header : array();

        if ($rawBody === false) {
            $error = error_get_last();
            return array(
                'status_code' => 0,
                'body' => '',
                'json' => array(),
                'headers' => $responseHeaders,
                'error' => isset($error['message']) ? $error['message'] : 'Unable to contact gateway.',
            );
        }

        return $this->normalizeHttpResponse(array(
            'status_code' => $this->statusCodeFromHeaders($responseHeaders),
            'body' => $rawBody,
            'headers' => $responseHeaders,
        ));
    }

    /**
     * Normalize an injected or stream HTTP response.
     *
     * @param mixed $response Raw response.
     * @return array
     */
    protected function normalizeHttpResponse($response)
    {
        if (! is_array($response)) {
            $response = array('status_code' => 0, 'body' => '', 'error' => 'Invalid HTTP client response.');
        }

        $body = isset($response['body']) ? $response['body'] : '';
        $json = isset($response['json']) && is_array($response['json']) ? $response['json'] : null;

        if ($json === null && is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);
            $json = is_array($decoded) ? $decoded : array();
        }

        if ($json === null) {
            $json = array();
        }

        return array(
            'status_code' => isset($response['status_code']) ? (int) $response['status_code'] : 0,
            'body' => $body,
            'json' => $json,
            'headers' => isset($response['headers']) && is_array($response['headers']) ? $response['headers'] : array(),
            'error' => isset($response['error']) ? $response['error'] : null,
        );
    }

    /**
     * Determine whether an HTTP status code represents a successful response.
     *
     * @param array $response HTTP response.
     * @return bool
     */
    protected function httpOk(array $response)
    {
        return isset($response['status_code']) && $response['status_code'] >= 200 && $response['status_code'] < 300;
    }

    /**
     * Extract the HTTP status code from PHP stream response headers.
     *
     * @param array $headers Response headers.
     * @return int
     */
    private function statusCodeFromHeaders(array $headers)
    {
        if (! isset($headers[0]) || ! preg_match('/\s(\d{3})\s/', $headers[0], $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * Convert request items into gateway-friendly name, price, and quantity rows.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @return array
     */
    protected function lineItems(PaymentRequest $request)
    {
        $items = array();

        foreach ($request->getItems() as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 1;
            $price = isset($item['price']) ? (int) $item['price'] : (isset($item['amount']) ? (int) $item['amount'] : 0);
            $name = isset($item['name']) ? $item['name'] : 'Item ' . ($index + 1);

            if ($price <= 0 || $quantity <= 0) {
                continue;
            }

            $items[] = array(
                'id' => isset($item['id']) ? (string) $item['id'] : (string) ($index + 1),
                'name' => $name,
                'price' => $price,
                'quantity' => $quantity,
            );
        }

        if (count($items) === 0) {
            $items[] = array(
                'id' => $request->getOrderId(),
                'name' => $request->getDescription() ?: 'Payment ' . $request->getOrderId(),
                'price' => $request->getAmount(),
                'quantity' => 1,
            );
        }

        return $items;
    }
}
