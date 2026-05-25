<?php

namespace Mhmfajar\PaymentOrchestrator\Support;

use Mhmfajar\PaymentOrchestrator\Constants\PaymentStatus;

/**
 * Maps gateway-specific statuses into the package's universal status values.
 */
class StatusMapper
{
    /**
     * Map a gateway-specific status value by gateway name.
     *
     * @param string $gateway Gateway name.
     * @param string $status Gateway-specific status value.
     * @return string Universal payment status.
     */
    public function map($gateway, $status)
    {
        if ($gateway === 'midtrans') {
            return $this->mapMidtrans($status);
        }

        if ($gateway === 'duitku') {
            return $this->mapDuitku($status);
        }

        if ($gateway === 'xendit') {
            return $this->mapXendit($status);
        }

        if ($gateway === 'doku') {
            return $this->mapDoku($status);
        }

        return PaymentStatus::FAILED;
    }

    /**
     * Map Midtrans transaction statuses.
     *
     * @param string $status Midtrans status value.
     * @return string Universal payment status.
     */
    public function mapMidtrans($status)
    {
        switch ($status) {
            case 'settlement':
            case 'capture':
                return PaymentStatus::PAID;
            case 'pending':
                return PaymentStatus::PENDING;
            case 'expire':
                return PaymentStatus::EXPIRED;
            case 'cancel':
                return PaymentStatus::CANCELLED;
            case 'deny':
                return PaymentStatus::FAILED;
            default:
                return PaymentStatus::FAILED;
        }
    }

    /**
     * Map Duitku result codes.
     *
     * @param string|int $statusCode Duitku status code.
     * @return string Universal payment status.
     */
    public function mapDuitku($statusCode)
    {
        switch ((string) $statusCode) {
            case '00':
                return PaymentStatus::PAID;
            case '01':
                return PaymentStatus::PENDING;
            case '02':
                return PaymentStatus::FAILED;
            default:
                return PaymentStatus::FAILED;
        }
    }

    /**
     * Map Xendit invoice/payment statuses.
     *
     * @param string $status Xendit status value.
     * @return string Universal payment status.
     */
    public function mapXendit($status)
    {
        switch ($status) {
            case 'PAID':
            case 'SETTLED':
                return PaymentStatus::PAID;
            case 'PENDING':
                return PaymentStatus::PENDING;
            case 'EXPIRED':
                return PaymentStatus::EXPIRED;
            default:
                return PaymentStatus::FAILED;
        }
    }

    /**
     * Map Doku payment statuses.
     *
     * @param string $status Doku status value.
     * @return string Universal payment status.
     */
    public function mapDoku($status)
    {
        switch ($status) {
            case 'SUCCESS':
            case 'PAID':
                return PaymentStatus::PAID;
            case 'PENDING':
                return PaymentStatus::PENDING;
            case 'EXPIRED':
                return PaymentStatus::EXPIRED;
            default:
                return PaymentStatus::FAILED;
        }
    }
}
