<?php

namespace Mhmfajar\PaymentOrchestrator\Contracts;

use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;

/**
 * Defines persistence operations for payments and gateway attempts.
 */
interface PaymentStoreInterface
{
    /**
     * Find one payment by application order ID.
     *
     * @param string $orderId Application order identifier.
     * @return array|null Payment row or null when not found.
     */
    public function findPaymentByOrderId($orderId);

    /**
     * Persist a new payment row.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @return array Created payment row.
     */
    public function createPayment(PaymentRequest $request);

    /**
     * Update the stored payment status and related fields.
     *
     * @param string $orderId Application order identifier.
     * @param string $status Universal payment status.
     * @param array $data Additional store-specific update data.
     * @return array|null Updated payment row.
     */
    public function updatePaymentStatus($orderId, $status, array $data = array());

    /**
     * Find the active attempt for a payment order.
     *
     * @param string $orderId Application order identifier.
     * @return array|null Active attempt row or null when absent.
     */
    public function findActiveAttempt($orderId);

    /**
     * Persist one gateway attempt.
     *
     * @param string $orderId Application order identifier.
     * @param string $gateway Gateway name.
     * @param PaymentRequest $request Normalized payment request.
     * @param PaymentResponse $response Normalized gateway response.
     * @return array Created attempt row.
     */
    public function createAttempt($orderId, $gateway, PaymentRequest $request, PaymentResponse $response);

    /**
     * Mark an attempt as active for its payment.
     *
     * @param int|string $attemptId Attempt identifier.
     * @return array|null Updated attempt row.
     */
    public function markAttemptAsActive($attemptId);

    /**
     * Update an attempt's status and gateway details.
     *
     * @param int|string $attemptId Attempt identifier.
     * @param string $status Universal payment status.
     * @param array $data Additional attempt update data.
     * @return array|null Updated attempt row.
     */
    public function updateAttemptStatus($attemptId, $status, array $data = array());
}
