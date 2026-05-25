<?php

namespace Mhmfajar\PaymentOrchestrator\Support;

use Mhmfajar\PaymentOrchestrator\Constants\GatewayFailureReason;
use Mhmfajar\PaymentOrchestrator\Constants\PaymentStatus;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;

/**
 * Centralizes the safety rules for deciding if another gateway may be tried.
 */
class FallbackPolicy
{
    /**
     * Whether fallback is enabled globally.
     *
     * @var bool
     */
    private $enabled;

    /**
     * Failure reasons that are safe for fallback.
     *
     * @var array
     */
    private $allowedFailureReasons;

    /**
     * Statuses that must block fallback.
     *
     * @var array
     */
    private $blockedStatuses;

    /**
     * Create fallback policy from configuration.
     *
     * @param array $config Fallback configuration.
     * @return void
     */
    public function __construct(array $config = array())
    {
        $this->enabled = array_key_exists('enabled', $config) ? (bool) $config['enabled'] : true;
        $this->allowedFailureReasons = isset($config['allowed_failure_reasons']) ? $config['allowed_failure_reasons'] : GatewayFailureReason::fallbackAllowed();
        $this->blockedStatuses = isset($config['blocked_statuses']) ? $config['blocked_statuses'] : array(PaymentStatus::PENDING, PaymentStatus::PAID, PaymentStatus::EXPIRED, PaymentStatus::CANCELLED, PaymentStatus::REFUNDED);
    }

    /**
     * Decide whether a failed response can safely try another gateway.
     *
     * @param PaymentResponse $response Gateway payment response.
     * @return bool True when fallback is safe.
     */
    public function canFallback(PaymentResponse $response)
    {
        // Never fallback once a gateway appears to have created something payable.
        if (! $this->enabled || $response->hasPayableTransaction()) {
            return false;
        }

        if ($response->getStatus() !== null && in_array($response->getStatus(), $this->blockedStatuses, true)) {
            return false;
        }

        return $response->isFallbackAllowed()
            && in_array($response->getFailureReason(), $this->allowedFailureReasons, true);
    }
}
