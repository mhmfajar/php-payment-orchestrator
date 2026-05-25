<?php

namespace Mhmfajar\PaymentOrchestrator\Managers;

use Exception;
use Mhmfajar\PaymentOrchestrator\Constants\GatewayFailureReason;
use Mhmfajar\PaymentOrchestrator\Constants\PaymentStatus;
use Mhmfajar\PaymentOrchestrator\Contracts\EventDispatcherInterface;
use Mhmfajar\PaymentOrchestrator\Contracts\PaymentStoreInterface;
use Mhmfajar\PaymentOrchestrator\DTO\CallbackResponse;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;
use Mhmfajar\PaymentOrchestrator\Events\PaymentCallbackReceived;
use Mhmfajar\PaymentOrchestrator\Events\PaymentCreated;
use Mhmfajar\PaymentOrchestrator\Events\PaymentExpired;
use Mhmfajar\PaymentOrchestrator\Events\PaymentFailed;
use Mhmfajar\PaymentOrchestrator\Events\PaymentFallbackTriggered;
use Mhmfajar\PaymentOrchestrator\Events\PaymentPaid;
use Mhmfajar\PaymentOrchestrator\Exceptions\FallbackNotAllowedException;
use Mhmfajar\PaymentOrchestrator\Exceptions\InvalidCallbackSignatureException;
use Mhmfajar\PaymentOrchestrator\Exceptions\PaymentException;
use Mhmfajar\PaymentOrchestrator\Support\FallbackPolicy;
use Psr\Log\LoggerInterface;

/**
 * Coordinates payment creation, safe fallback, attempt persistence, and callbacks.
 */
class PaymentManager
{
    /**
     * Persistence boundary for payments and attempts.
     *
     * @var PaymentStoreInterface
     */
    private $store;

    /**
     * Resolves gateway drivers and fallback order.
     *
     * @var GatewayManager
     */
    private $gatewayManager;

    /**
     * Determines whether retrying another gateway is safe.
     *
     * @var FallbackPolicy
     */
    private $fallbackPolicy;

    /**
     * Dispatches payment lifecycle events.
     *
     * @var EventDispatcherInterface
     */
    private $events;

    /**
     * Records gateway exceptions without leaking secrets.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Create the payment manager with explicit infrastructure dependencies.
     *
     * @param PaymentStoreInterface $store Payment and attempt store.
     * @param GatewayManager $gatewayManager Gateway resolver.
     * @param FallbackPolicy $fallbackPolicy Fallback safety policy.
     * @param EventDispatcherInterface $events Lifecycle event dispatcher.
     * @param LoggerInterface $logger Logger for gateway exceptions.
     * @return void
     */
    public function __construct(PaymentStoreInterface $store, GatewayManager $gatewayManager, FallbackPolicy $fallbackPolicy, EventDispatcherInterface $events, LoggerInterface $logger)
    {
        $this->store = $store;
        $this->gatewayManager = $gatewayManager;
        $this->fallbackPolicy = $fallbackPolicy;
        $this->events = $events;
        $this->logger = $logger;
    }

    /**
     * Create or reuse a payment and safely attempt configured gateways.
     *
     * @param array|PaymentRequest $payload Payment request payload.
     * @return PaymentResponse Successful or existing payment response.
     * @throws FallbackNotAllowedException When fallback would be unsafe.
     * @throws PaymentException When all fallback attempts fail.
     */
    public function create($payload)
    {
        // $request is the normalized input that all gateways and stores share.
        $request = $payload instanceof PaymentRequest ? $payload : PaymentRequest::fromArray($payload);

        // $payment is the persisted order-level record used to prevent duplicate attempts.
        $payment = $this->store->findPaymentByOrderId($request->getOrderId());

        if (! $payment) {
            $payment = $this->store->createPayment($request);
        }

        if (isset($payment['status']) && $payment['status'] === PaymentStatus::PAID) {
            return $this->responseFromPayment($payment);
        }

        // Reuse an existing pending attempt so the same order does not get duplicate payment links.
        $activeAttempt = $this->store->findActiveAttempt($request->getOrderId());
        if ($activeAttempt && isset($activeAttempt['status']) && $activeAttempt['status'] === PaymentStatus::PENDING) {
            return $this->responseFromAttempt($activeAttempt, $request->getOrderId());
        }

        // $lastResponse keeps the final gateway failure for a useful exception message.
        $lastResponse = null;

        // $gatewaySequence is ordered by preferred/default gateway then configured fallbacks.
        $gatewaySequence = $this->gatewayManager->sequence($request->getPreferredGateway());

        foreach ($gatewaySequence as $gatewayName) {
            $gateway = $this->gatewayManager->driver($gatewayName);
            $health = $gateway->healthCheck();

            if (! $health->isAvailable()) {
                // Failed health checks are safe to record as attempts because no payable transaction was created.
                $lastResponse = new PaymentResponse(array(
                    'success' => false,
                    'gateway' => $gatewayName,
                    'order_id' => $request->getOrderId(),
                    'message' => $health->getMessage(),
                    'failure_reason' => GatewayFailureReason::HEALTH_CHECK_FAILED,
                    'fallback_allowed' => true,
                    'raw' => $health->getRaw(),
                ));

                $this->store->createAttempt($request->getOrderId(), $gatewayName, $request, $lastResponse);
                $this->events->dispatch(new PaymentFallbackTriggered($payment, $gatewayName, GatewayFailureReason::HEALTH_CHECK_FAILED));
                continue;
            }

            try {
                $response = $gateway->createPayment($request);
            } catch (Exception $exception) {
                $this->logger->warning('Payment gateway exception.', array('gateway' => $gatewayName, 'exception' => $exception->getMessage()));
                $response = new PaymentResponse(array(
                    'success' => false,
                    'gateway' => $gatewayName,
                    'order_id' => $request->getOrderId(),
                    'message' => $exception->getMessage(),
                    'failure_reason' => GatewayFailureReason::GATEWAY_UNAVAILABLE,
                    'fallback_allowed' => true,
                ));
            }

            $lastResponse = $response;
            $attempt = $this->store->createAttempt($request->getOrderId(), $gatewayName, $request, $response);

            if ($response->isSuccess() && $response->hasPayableTransaction()) {
                // Once a payable transaction exists, mark it active and stop the fallback sequence.
                $this->store->markAttemptAsActive($attempt['id']);
                $payment = $this->store->updatePaymentStatus($request->getOrderId(), PaymentStatus::PENDING, array('active_gateway' => $gatewayName));
                $activeAttempt = $this->store->findActiveAttempt($request->getOrderId());
                $this->events->dispatch(new PaymentCreated($payment, $activeAttempt ?: $attempt));

                return $response;
            }

            if (! $this->fallbackPolicy->canFallback($response)) {
                throw new FallbackNotAllowedException($response->getMessage() ?: 'Fallback is not allowed for this payment response.');
            }

            $this->events->dispatch(new PaymentFallbackTriggered($payment, $gatewayName, $response->getFailureReason()));
        }

        $payment = $this->store->updatePaymentStatus($request->getOrderId(), PaymentStatus::FAILED);
        $this->events->dispatch(new PaymentFailed($payment));

        throw new PaymentException($lastResponse ? ($lastResponse->getMessage() ?: 'All payment gateways failed.') : 'All payment gateways failed.');
    }

    /**
     * Verify, normalize, persist, and dispatch a gateway callback.
     *
     * @param string $gatewayName Gateway name.
     * @param array $payload Raw callback payload.
     * @return CallbackResponse Normalized callback response.
     * @throws InvalidCallbackSignatureException When gateway verification fails.
     */
    public function handleCallback($gatewayName, array $payload)
    {
        // Callback verification is delegated to each gateway because signatures differ by provider.
        $gateway = $this->gatewayManager->driver($gatewayName);
        $response = $gateway->handleCallback($payload);

        if (! $response->isValid()) {
            throw new InvalidCallbackSignatureException('Invalid callback signature for gateway [' . $gatewayName . '].');
        }

        $payment = $this->store->updatePaymentStatus($response->getOrderId(), $response->getStatus(), array('active_gateway' => $gatewayName));
        $activeAttempt = $this->store->findActiveAttempt($response->getOrderId());

        if ($activeAttempt) {
            $this->store->updateAttemptStatus($activeAttempt['id'], $response->getStatus(), array(
                'gateway_transaction_id' => $response->getTransactionId(),
                'gateway_order_id' => $response->getGatewayOrderId(),
                'raw_response' => $response->getRaw(),
            ));
        }

        $this->events->dispatch(new PaymentCallbackReceived($response));
        $this->dispatchStatusEvent($payment, $response);

        return $response;
    }

    /**
     * Dispatch the status-specific lifecycle event for a callback response.
     *
     * @param array $payment Payment row.
     * @param CallbackResponse $response Normalized callback response.
     * @return void
     */
    private function dispatchStatusEvent(array $payment, CallbackResponse $response)
    {
        if ($response->getStatus() === PaymentStatus::PAID) {
            $this->events->dispatch(new PaymentPaid($payment));
        } elseif ($response->getStatus() === PaymentStatus::EXPIRED) {
            $this->events->dispatch(new PaymentExpired($payment));
        } elseif ($response->getStatus() === PaymentStatus::FAILED) {
            $this->events->dispatch(new PaymentFailed($payment));
        }
    }

    /**
     * Convert an existing payment row into a response.
     *
     * @param array $payment Payment row.
     * @return PaymentResponse Normalized payment response.
     */
    private function responseFromPayment(array $payment)
    {
        return new PaymentResponse(array(
            'success' => true,
            'gateway' => isset($payment['active_gateway']) ? $payment['active_gateway'] : '',
            'order_id' => isset($payment['order_id']) ? $payment['order_id'] : '',
            'status' => isset($payment['status']) ? $payment['status'] : null,
        ));
    }

    /**
     * Convert an active attempt row into a response.
     *
     * @param array $attempt Attempt row.
     * @param string $orderId Application order identifier.
     * @return PaymentResponse Normalized payment response.
     */
    private function responseFromAttempt(array $attempt, $orderId)
    {
        return new PaymentResponse(array(
            'success' => true,
            'gateway' => isset($attempt['gateway']) ? $attempt['gateway'] : '',
            'order_id' => $orderId,
            'status' => isset($attempt['status']) ? $attempt['status'] : PaymentStatus::PENDING,
            'transaction_id' => isset($attempt['gateway_transaction_id']) ? $attempt['gateway_transaction_id'] : null,
            'gateway_order_id' => isset($attempt['gateway_order_id']) ? $attempt['gateway_order_id'] : null,
            'payment_url' => isset($attempt['payment_url']) ? $attempt['payment_url'] : null,
            'qr_string' => isset($attempt['qr_string']) ? $attempt['qr_string'] : null,
            'va_number' => isset($attempt['va_number']) ? $attempt['va_number'] : null,
            'raw' => isset($attempt['raw_response']) && is_array($attempt['raw_response']) ? $attempt['raw_response'] : array(),
        ));
    }
}
