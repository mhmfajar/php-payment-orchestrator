<?php

namespace Mhmfajar\PaymentOrchestrator\Storage;

use Mhmfajar\PaymentOrchestrator\Constants\PaymentStatus;
use Mhmfajar\PaymentOrchestrator\Contracts\PaymentStoreInterface;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;
use PDO;

/**
 * PDO-backed store for native PHP applications that use relational databases.
 */
class PdoPaymentStore implements PaymentStoreInterface
{
    /**
     * Database connection used for native PHP storage.
     *
     * @var PDO
     */
    private $pdo;

    /**
     * Sanitized table mapping for payments and attempts.
     *
     * @var array
     */
    private $tables;

    /**
     * Create a PDO-backed store with optional custom table names.
     *
     * @param PDO $pdo Database connection.
     * @param array $tables Table name mapping.
     * @return void
     */
    public function __construct(PDO $pdo, array $tables = array())
    {
        $this->pdo = $pdo;
        $this->tables = array(
            'payments' => isset($tables['payments']) ? $tables['payments'] : 'payments',
            'payment_attempts' => isset($tables['payment_attempts']) ? $tables['payment_attempts'] : 'payment_attempts',
        );
    }

    /**
     * Find a payment row by application order ID.
     *
     * @param string $orderId Application order identifier.
     * @return array|null Payment row.
     */
    public function findPaymentByOrderId($orderId)
    {
        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table('payments') . ' WHERE order_id = :order_id LIMIT 1');
        $statement->execute(array('order_id' => $orderId));
        $payment = $statement->fetch(PDO::FETCH_ASSOC);

        return $payment ?: null;
    }

    /**
     * Insert a payment row from a normalized request.
     *
     * @param PaymentRequest $request Normalized payment request.
     * @return array|null Created payment row.
     */
    public function createPayment(PaymentRequest $request)
    {
        $now = date('Y-m-d H:i:s');
        $statement = $this->pdo->prepare('INSERT INTO ' . $this->table('payments') . ' (order_id, amount, currency, status, customer_name, customer_email, customer_phone, items, metadata, created_at, updated_at) VALUES (:order_id, :amount, :currency, :status, :customer_name, :customer_email, :customer_phone, :items, :metadata, :created_at, :updated_at)');
        $statement->execute(array(
            'order_id' => $request->getOrderId(),
            'amount' => $request->getAmount(),
            'currency' => $request->getCurrency(),
            'status' => PaymentStatus::PENDING,
            'customer_name' => $request->getCustomerName(),
            'customer_email' => $request->getCustomerEmail(),
            'customer_phone' => $request->getCustomerPhone(),
            'items' => json_encode($request->getItems()),
            'metadata' => json_encode($request->getMetadata()),
            'created_at' => $now,
            'updated_at' => $now,
        ));

        return $this->findPaymentByOrderId($request->getOrderId());
    }

    /**
     * Update payment status and a restricted set of mutable columns.
     *
     * @param string $orderId Application order identifier.
     * @param string $status Universal payment status.
     * @param array $data Additional payment update data.
     * @return array|null Updated payment row.
     */
    public function updatePaymentStatus($orderId, $status, array $data = array())
    {
        // $allowed prevents arbitrary payload keys from becoming SQL column names.
        $allowed = array('active_gateway', 'paid_at', 'expired_at');
        $sets = array('status = :status', 'updated_at = :updated_at');
        $params = array('status' => $status, 'updated_at' => date('Y-m-d H:i:s'), 'order_id' => $orderId);

        if ($status === PaymentStatus::PAID) {
            $sets[] = 'paid_at = :paid_at';
            $params['paid_at'] = date('Y-m-d H:i:s');
        }

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $sets[] = $key . ' = :' . $key;
                $params[$key] = $value;
            }
        }

        $statement = $this->pdo->prepare('UPDATE ' . $this->table('payments') . ' SET ' . implode(', ', $sets) . ' WHERE order_id = :order_id');
        $statement->execute($params);

        return $this->findPaymentByOrderId($orderId);
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

        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table('payment_attempts') . ' WHERE payment_id = :payment_id AND is_active = 1 ORDER BY id DESC LIMIT 1');
        $statement->execute(array('payment_id' => $payment['id']));
        $attempt = $statement->fetch(PDO::FETCH_ASSOC);

        return $attempt ?: null;
    }

    /**
     * Insert a payment attempt row from request and gateway response data.
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

        $now = date('Y-m-d H:i:s');
        $statement = $this->pdo->prepare('INSERT INTO ' . $this->table('payment_attempts') . ' (payment_id, gateway, gateway_order_id, gateway_transaction_id, status, payment_url, qr_string, va_number, error_message, failure_reason, is_active, raw_request, raw_response, created_at, updated_at) VALUES (:payment_id, :gateway, :gateway_order_id, :gateway_transaction_id, :status, :payment_url, :qr_string, :va_number, :error_message, :failure_reason, :is_active, :raw_request, :raw_response, :created_at, :updated_at)');
        $statement->execute(array(
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
            'is_active' => 0,
            'raw_request' => json_encode(array('order_id' => $request->getOrderId(), 'amount' => $request->getAmount())),
            'raw_response' => json_encode($response->getRaw()),
            'created_at' => $now,
            'updated_at' => $now,
        ));

        $id = $this->pdo->lastInsertId();
        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table('payment_attempts') . ' WHERE id = :id');
        $statement->execute(array('id' => $id));

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mark one attempt active and deactivate sibling attempts.
     *
     * @param int|string $attemptId Attempt identifier.
     * @return array|null Updated attempt row.
     */
    public function markAttemptAsActive($attemptId)
    {
        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table('payment_attempts') . ' WHERE id = :id');
        $statement->execute(array('id' => $attemptId));
        $attempt = $statement->fetch(PDO::FETCH_ASSOC);

        if (! $attempt) {
            return null;
        }

        // Deactivate sibling attempts before activating the selected attempt.
        $this->pdo->prepare('UPDATE ' . $this->table('payment_attempts') . ' SET is_active = 0 WHERE payment_id = :payment_id')->execute(array('payment_id' => $attempt['payment_id']));
        $this->pdo->prepare('UPDATE ' . $this->table('payment_attempts') . ' SET is_active = 1, updated_at = :updated_at WHERE id = :id')->execute(array('updated_at' => date('Y-m-d H:i:s'), 'id' => $attemptId));

        $statement->execute(array('id' => $attemptId));
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update attempt status and a restricted set of gateway result columns.
     *
     * @param int|string $attemptId Attempt identifier.
     * @param string $status Universal payment status.
     * @param array $data Additional attempt update data.
     * @return array|null Updated attempt row.
     */
    public function updateAttemptStatus($attemptId, $status, array $data = array())
    {
        // $allowed prevents arbitrary payload keys from becoming SQL column names.
        $allowed = array('gateway_order_id', 'gateway_transaction_id', 'payment_url', 'qr_string', 'va_number', 'error_code', 'error_message', 'failure_reason', 'raw_response');
        $sets = array('status = :status', 'updated_at = :updated_at');
        $params = array('status' => $status, 'updated_at' => date('Y-m-d H:i:s'), 'id' => $attemptId);

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $sets[] = $key . ' = :' . $key;
                $params[$key] = is_array($value) ? json_encode($value) : $value;
            }
        }

        $this->pdo->prepare('UPDATE ' . $this->table('payment_attempts') . ' SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

        $statement = $this->pdo->prepare('SELECT * FROM ' . $this->table('payment_attempts') . ' WHERE id = :id');
        $statement->execute(array('id' => $attemptId));

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Return a sanitized table name by logical key.
     *
     * @param string $key Logical table key.
     * @return string Sanitized table name.
     */
    private function table($key)
    {
        // Table names cannot be bound as parameters, so strip unsafe characters before interpolation.
        return preg_replace('/[^A-Za-z0-9_]/', '', $this->tables[$key]);
    }
}
