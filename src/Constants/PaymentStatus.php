<?php

namespace Mhmfajar\PaymentOrchestrator\Constants;

/**
 * Universal payment statuses shared by gateways, storage, and callbacks.
 */
class PaymentStatus
{
    /**
     * Payment is waiting for customer action or gateway settlement.
     *
     * @var string
     */
    const PENDING = 'pending';

    /**
     * Payment has been completed successfully.
     *
     * @var string
     */
    const PAID = 'paid';

    /**
     * Payment failed.
     *
     * @var string
     */
    const FAILED = 'failed';

    /**
     * Payment expired before completion.
     *
     * @var string
     */
    const EXPIRED = 'expired';

    /**
     * Payment was cancelled.
     *
     * @var string
     */
    const CANCELLED = 'cancelled';

    /**
     * Payment was refunded.
     *
     * @var string
     */
    const REFUNDED = 'refunded';

    /**
     * Return every universal payment status.
     *
     * @return array
     */
    public static function all()
    {
        return array(self::PENDING, self::PAID, self::FAILED, self::EXPIRED, self::CANCELLED, self::REFUNDED);
    }
}
