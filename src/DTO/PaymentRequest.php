<?php

namespace Mhmfajar\PaymentOrchestrator\DTO;

use InvalidArgumentException;

/**
 * Carries normalized payment creation input through the orchestration flow.
 */
class PaymentRequest
{
    /**
     * Application order identifier for the payment.
     *
     * @var string
     */
    private $orderId;

    /**
     * Payment amount in the smallest gateway-supported unit for the currency.
     *
     * @var int
     */
    private $amount;

    /**
     * Customer display name sent to gateways.
     *
     * @var string
     */
    private $customerName;

    /**
     * Customer email sent to gateways.
     *
     * @var string
     */
    private $customerEmail;

    /**
     * Optional customer phone number.
     *
     * @var string|null
     */
    private $customerPhone;

    /**
     * Currency code, defaults to IDR.
     *
     * @var string
     */
    private $currency;

    /**
     * Line items or gateway-specific item details.
     *
     * @var array
     */
    private $items;

    /**
     * Application metadata stored with the payment.
     *
     * @var array
     */
    private $metadata;

    /**
     * Optional gateway name that should be tried first.
     *
     * @var string|null
     */
    private $preferredGateway;

    /**
     * Optional payment description.
     *
     * @var string|null
     */
    private $description;

    /**
     * Optional callback URL override.
     *
     * @var string|null
     */
    private $callbackUrl;

    /**
     * Optional return URL override.
     *
     * @var string|null
     */
    private $returnUrl;

    /**
     * Create a validated payment request DTO.
     *
     * @param string $orderId Application order identifier.
     * @param int|string $amount Payment amount.
     * @param string $customerName Customer display name.
     * @param string $customerEmail Customer email address.
     * @param string|null $customerPhone Optional customer phone number.
     * @param string $currency Currency code.
     * @param array $items Optional line items.
     * @param array $metadata Optional application metadata.
     * @param string|null $preferredGateway Optional first gateway to try.
     * @param string|null $description Optional payment description.
     * @param string|null $callbackUrl Optional callback URL override.
     * @param string|null $returnUrl Optional return URL override.
     * @return void
     * @throws InvalidArgumentException When required request data is missing or invalid.
     */
    public function __construct($orderId, $amount, $customerName, $customerEmail, $customerPhone = null, $currency = 'IDR', array $items = array(), array $metadata = array(), $preferredGateway = null, $description = null, $callbackUrl = null, $returnUrl = null)
    {
        // Validate early so invalid user input never reaches gateway fallback logic.
        if ($orderId === null || $orderId === '') {
            throw new InvalidArgumentException('order_id is required.');
        }

        if ((int) $amount <= 0) {
            throw new InvalidArgumentException('amount must be greater than zero.');
        }

        if ($customerName === null || $customerName === '') {
            throw new InvalidArgumentException('customer_name is required.');
        }

        if ($customerEmail === null || $customerEmail === '') {
            throw new InvalidArgumentException('customer_email is required.');
        }

        $this->orderId = $orderId;
        $this->amount = (int) $amount;
        $this->customerName = $customerName;
        $this->customerEmail = $customerEmail;
        $this->customerPhone = $customerPhone;
        $this->currency = $currency;
        $this->items = $items;
        $this->metadata = $metadata;
        $this->preferredGateway = $preferredGateway;
        $this->description = $description;
        $this->callbackUrl = $callbackUrl;
        $this->returnUrl = $returnUrl;
    }

    /**
     * Build a payment request from user-facing array input.
     *
     * @param array $payload User-facing payment request payload.
     * @return self Normalized payment request.
     * @throws InvalidArgumentException When required request data is missing or invalid.
     */
    public static function fromArray(array $payload)
    {
        return new self(
            isset($payload['order_id']) ? $payload['order_id'] : null,
            isset($payload['amount']) ? $payload['amount'] : 0,
            isset($payload['customer_name']) ? $payload['customer_name'] : null,
            isset($payload['customer_email']) ? $payload['customer_email'] : null,
            isset($payload['customer_phone']) ? $payload['customer_phone'] : null,
            isset($payload['currency']) ? $payload['currency'] : 'IDR',
            isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array(),
            isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : array(),
            isset($payload['preferred_gateway']) ? $payload['preferred_gateway'] : null,
            isset($payload['description']) ? $payload['description'] : null,
            isset($payload['callback_url']) ? $payload['callback_url'] : null,
            isset($payload['return_url']) ? $payload['return_url'] : null
        );
    }

    /**
     * Return the application order identifier.
     *
     * @return string
     */
    public function getOrderId() { return $this->orderId; }

    /**
     * Return the payment amount.
     *
     * @return int
     */
    public function getAmount() { return $this->amount; }

    /**
     * Return the customer name.
     *
     * @return string
     */
    public function getCustomerName() { return $this->customerName; }

    /**
     * Return the customer email.
     *
     * @return string
     */
    public function getCustomerEmail() { return $this->customerEmail; }

    /**
     * Return the optional customer phone number.
     *
     * @return string|null
     */
    public function getCustomerPhone() { return $this->customerPhone; }

    /**
     * Return the currency code.
     *
     * @return string
     */
    public function getCurrency() { return $this->currency; }

    /**
     * Return the request line items.
     *
     * @return array
     */
    public function getItems() { return $this->items; }

    /**
     * Return application metadata.
     *
     * @return array
     */
    public function getMetadata() { return $this->metadata; }

    /**
     * Return the gateway that should be tried first, when provided.
     *
     * @return string|null
     */
    public function getPreferredGateway() { return $this->preferredGateway; }

    /**
     * Return the optional payment description.
     *
     * @return string|null
     */
    public function getDescription() { return $this->description; }

    /**
     * Return the optional callback URL override.
     *
     * @return string|null
     */
    public function getCallbackUrl() { return $this->callbackUrl; }

    /**
     * Return the optional return URL override.
     *
     * @return string|null
     */
    public function getReturnUrl() { return $this->returnUrl; }
}
