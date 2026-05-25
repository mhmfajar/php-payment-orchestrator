<?php

namespace Mhmfajar\PaymentOrchestrator\Storage;

use Mhmfajar\PaymentOrchestrator\Constants\PaymentStatus;
use Mhmfajar\PaymentOrchestrator\Contracts\PaymentStoreInterface;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;

/**
 * In-process payment store for tests and simple native PHP examples.
 */
class InMemoryPaymentStore implements PaymentStoreInterface
{
    /**
     * Payment rows keyed by order ID.
     *
     * @var array
     */
    private $payments = array();

    /**
     * Attempt rows keyed by attempt ID.
     *
     * @var array
     */
    private $attempts = array();

    /**
     * Auto-incrementing payment ID for in-memory rows.
     *
     * @var int
     */
    private $paymentIncrement = 1;

    /**
     * Auto-incrementing attempt ID for in-memory rows.
     *
     * @var int
     */
    private $attemptIncrement = 1;

    /**
     * Find a payment row by application order ID.
     *
     * @param string $orderId Application order identifier.
     * @return array|null Payment row.
     */
    public function findPaymentByOrderId($orderId)
    {
        return isset($this->payments[$orderId]) ? $this->payments[$orderId] : null;
    }

    /**
     * Create a payment row from a normalized request.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @return array Created payment row.
     */
    public function createPayment(PaymentRequest $request)
    {
        $payment = array(
            'id' => $this->paymentIncrement++,
            'order_id' => $request->getOrderId(),
            'amount' => $request->getAmount(),
            'currency' => $request->getCurrency(),
            'status' => PaymentStatus::PENDING,
            'active_gateway' => null,
            'customer_name' => $request->getCustomerName(),
            'customer_email' => $request->getCustomerEmail(),
            'customer_phone' => $request->getCustomerPhone(),
            'items' => $request->getItems(),
            'metadata' => $request->getMetadata(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        );

        $this->payments[$request->getOrderId()] = $payment;

        return $payment;
    }

    /**
     * Update payment status and allowed metadata fields.
     *
     * @param string $orderId Application order identifier.
     * @param string $status Universal payment status.
     * @param array $data Additional payment update data.
     * @return array Updated payment row.
     */
    public function updatePaymentStatus($orderId, $status, array $data = array())
    {
        if (! isset($this->payments[$orderId])) {
            $this->payments[$orderId] = array('id' => $this->paymentIncrement++, 'order_id' => $orderId);
        }

        $this->payments[$orderId]['status'] = $status;
        $this->payments[$orderId]['updated_at'] = date('Y-m-d H:i:s');

        foreach ($data as $key => $value) {
            $this->payments[$orderId][$key] = $value;
        }

        if ($status === PaymentStatus::PAID) {
            $this->payments[$orderId]['paid_at'] = date('Y-m-d H:i:s');
        }

        return $this->payments[$orderId];
    }

    /**
     * Return the active attempt for an order, if one exists.
     *
     * @param string $orderId Application order identifier.
     * @return array|null Active attempt row.
     */
    public function findActiveAttempt($orderId)
    {
        $payment = $this->findPaymentByOrderId($orderId);
        if (! $payment) {
            return null;
        }

        foreach ($this->attempts as $attempt) {
            if ($attempt['payment_id'] === $payment['id'] && $attempt['is_active']) {
                return $attempt;
            }
        }

        return null;
    }

    /**
     * Store a gateway attempt for an order.
     *
     * @param string $orderId Application order identifier.
     * @param string $gateway Gateway name.
     * @param PaymentRequest $request Normalized payment request.
     * @param PaymentResponse $response Normalized gateway response.
     * @return array Created attempt row.
     */
    public function createAttempt($orderId, $gateway, PaymentRequest $request, PaymentResponse $response)
    {
        $payment = $this->findPaymentByOrderId($orderId);
        if (! $payment) {
            $payment = $this->createPayment($request);
        }

        $attempt = array(
            'id' => $this->attemptIncrement++,
            'payment_id' => $payment['id'],
            'gateway' => $gateway,
            'gateway_order_id' => $response->getGatewayOrderId(),
            'gateway_transaction_id' => $response->getTransactionId(),
            'status' => $response->getStatus() ?: ($response->isSuccess() ? PaymentStatus::PENDING : PaymentStatus::FAILED),
            'payment_url' => $response->getPaymentUrl(),
            'qr_string' => $response->getQrString(),
            'va_number' => $response->getVaNumber(),
            'error_message' => $response->getMessage(),
            'failure_reason' => $response->getFailureReason(),
            'is_active' => false,
            'raw_request' => array(
                'order_id' => $request->getOrderId(),
                'amount' => $request->getAmount(),
                'customer_email' => $request->getCustomerEmail(),
            ),
            'raw_response' => $response->getRaw(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        );

        $this->attempts[$attempt['id']] = $attempt;

        return $attempt;
    }

    /**
     * Mark one attempt active and deactivate sibling attempts.
     *
     * @param int $attemptId Attempt identifier.
     * @return array|null Updated attempt row.
     */
    public function markAttemptAsActive($attemptId)
    {
        if (! isset($this->attempts[$attemptId])) {
            return null;
        }

        // Only one active attempt is allowed for a payment at any time.
        $paymentId = $this->attempts[$attemptId]['payment_id'];

        foreach ($this->attempts as $id => $attempt) {
            if ($attempt['payment_id'] === $paymentId) {
                $this->attempts[$id]['is_active'] = false;
            }
        }

        $this->attempts[$attemptId]['is_active'] = true;
        $this->attempts[$attemptId]['updated_at'] = date('Y-m-d H:i:s');

        return $this->attempts[$attemptId];
    }

    /**
     * Update attempt status and selected gateway fields.
     *
     * @param int $attemptId Attempt identifier.
     * @param string $status Universal payment status.
     * @param array $data Additional attempt update data.
     * @return array|null Updated attempt row.
     */
    public function updateAttemptStatus($attemptId, $status, array $data = array())
    {
        if (! isset($this->attempts[$attemptId])) {
            return null;
        }

        $this->attempts[$attemptId]['status'] = $status;
        $this->attempts[$attemptId]['updated_at'] = date('Y-m-d H:i:s');

        foreach ($data as $key => $value) {
            $this->attempts[$attemptId][$key] = $value;
        }

        return $this->attempts[$attemptId];
    }

    /**
     * Return all in-memory payments for assertions and diagnostics.
     *
     * @return array Payment rows.
     */
    public function allPayments()
    {
        return $this->payments;
    }

    /**
     * Return all in-memory attempts for assertions and diagnostics.
     *
     * @return array Attempt rows.
     */
    public function allAttempts()
    {
        return $this->attempts;
    }
}
