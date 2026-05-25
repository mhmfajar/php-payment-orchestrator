<?php

namespace Mhmfajar\PaymentOrchestrator\DTO;

use Mhmfajar\PaymentOrchestrator\Constants\PaymentStatus;

/**
 * Represents a normalized gateway payment creation result.
 */
class PaymentResponse
{
    /**
     * Whether the gateway creation call succeeded.
     *
     * @var bool
     */
    private $success;

    /**
     * Gateway name that produced the response.
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
     * Universal payment status.
     *
     * @var string|null
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
     * Redirect or checkout URL for the customer.
     *
     * @var string|null
     */
    private $paymentUrl;

    /**
     * QR payment payload, when supplied by a gateway.
     *
     * @var string|null
     */
    private $qrString;

    /**
     * Virtual account number, when supplied by a gateway.
     *
     * @var string|null
     */
    private $vaNumber;

    /**
     * Human-readable gateway result message.
     *
     * @var string|null
     */
    private $message;

    /**
     * Normalized failure reason used by fallback policy.
     *
     * @var string|null
     */
    private $failureReason;

    /**
     * Whether this response may move to the next gateway.
     *
     * @var bool
     */
    private $fallbackAllowed;

    /**
     * Raw gateway response for diagnostics and persistence.
     *
     * @var array
     */
    private $raw;

    /**
     * Create a normalized payment response from gateway data.
     *
     * @param array $data Gateway response data.
     * @return void
     */
    public function __construct(array $data)
    {
        $this->success = isset($data['success']) ? (bool) $data['success'] : false;
        $this->gateway = isset($data['gateway']) ? $data['gateway'] : '';
        $this->orderId = isset($data['order_id']) ? $data['order_id'] : '';
        $this->status = isset($data['status']) ? $data['status'] : null;
        $this->transactionId = isset($data['transaction_id']) ? $data['transaction_id'] : null;
        $this->gatewayOrderId = isset($data['gateway_order_id']) ? $data['gateway_order_id'] : null;
        $this->paymentUrl = isset($data['payment_url']) ? $data['payment_url'] : null;
        $this->qrString = isset($data['qr_string']) ? $data['qr_string'] : null;
        $this->vaNumber = isset($data['va_number']) ? $data['va_number'] : null;
        $this->message = isset($data['message']) ? $data['message'] : null;
        $this->failureReason = isset($data['failure_reason']) ? $data['failure_reason'] : null;
        $this->fallbackAllowed = isset($data['fallback_allowed']) ? (bool) $data['fallback_allowed'] : false;
        $this->raw = isset($data['raw']) && is_array($data['raw']) ? $data['raw'] : array();
    }

    /**
     * Return whether the gateway request succeeded.
     *
     * @return bool
     */
    public function isSuccess() { return $this->success; }

    /**
     * Return the gateway name.
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
     * Return the universal payment status.
     *
     * @return string|null
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
     * Return the customer payment URL.
     *
     * @return string|null
     */
    public function getPaymentUrl() { return $this->paymentUrl; }

    /**
     * Return the QR payment payload.
     *
     * @return string|null
     */
    public function getQrString() { return $this->qrString; }

    /**
     * Return the virtual account number.
     *
     * @return string|null
     */
    public function getVaNumber() { return $this->vaNumber; }

    /**
     * Return the gateway message.
     *
     * @return string|null
     */
    public function getMessage() { return $this->message; }

    /**
     * Return the normalized failure reason.
     *
     * @return string|null
     */
    public function getFailureReason() { return $this->failureReason; }

    /**
     * Return whether fallback is explicitly allowed.
     *
     * @return bool
     */
    public function isFallbackAllowed() { return $this->fallbackAllowed; }

    /**
     * Return the raw gateway response.
     *
     * @return array
     */
    public function getRaw() { return $this->raw; }

    /**
     * Determine whether this response indicates that a payable transaction exists.
     *
     * @return bool
     */
    public function hasPayableTransaction()
    {
        // A payable transaction means fallback would risk creating duplicate payment links.
        return ! empty($this->paymentUrl)
            || ! empty($this->transactionId)
            || ! empty($this->gatewayOrderId)
            || $this->status === PaymentStatus::PENDING
            || $this->status === PaymentStatus::PAID;
    }
}
