<?php

namespace Mhmfajar\PaymentOrchestrator\Tests;

use Mhmfajar\PaymentOrchestrator\Constants\GatewayFailureReason;
use Mhmfajar\PaymentOrchestrator\Constants\PaymentStatus;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentResponse;
use Mhmfajar\PaymentOrchestrator\Exceptions\FallbackNotAllowedException;
use Mhmfajar\PaymentOrchestrator\Exceptions\InvalidCallbackSignatureException;
use Mhmfajar\PaymentOrchestrator\PaymentOrchestrator;
use Mhmfajar\PaymentOrchestrator\Storage\InMemoryPaymentStore;
use Mhmfajar\PaymentOrchestrator\Support\StatusMapper;
use Mhmfajar\PaymentOrchestrator\Tests\Fakes\FakeGateway;
use PHPUnit\Framework\TestCase;

/**
 * Verifies core orchestration safety behavior with fake gateways and in-memory storage.
 */
class PaymentOrchestratorTest extends TestCase
{
    /**
     * It uses the default gateway when no preferred gateway is supplied.
     *
     * @return void
     */
    public function testCreatesPaymentWithDefaultGateway()
    {
        $payment = PaymentOrchestrator::make($this->config());
        $payment->extend('midtrans', function () {
            return new FakeGateway('midtrans');
        });

        $response = $payment->create($this->payload());

        $this->assertTrue($response->isSuccess());
        $this->assertSame('midtrans', $response->getGateway());
        $this->assertSame('https://pay.example/INV-001', $response->getPaymentUrl());
    }

    /**
     * It uses the request preferred gateway as the primary gateway.
     *
     * @return void
     */
    public function testCreatesPaymentWithPreferredGateway()
    {
        $payment = PaymentOrchestrator::make($this->config());
        $payment->extend('duitku', function () {
            return new FakeGateway('duitku');
        });

        $payload = $this->payload(array('preferred_gateway' => 'duitku'));
        $response = $payment->create($payload);

        $this->assertSame('duitku', $response->getGateway());
    }

    /**
     * It falls back when the primary gateway health check fails.
     *
     * @return void
     */
    public function testFallbackWhenPrimaryGatewayIsUnavailable()
    {
        $payment = PaymentOrchestrator::make($this->config());
        $payment->extend('midtrans', function () {
            return new FakeGateway('midtrans', null, false);
        });
        $payment->extend('duitku', function () {
            return new FakeGateway('duitku');
        });

        $response = $payment->create($this->payload());

        $this->assertSame('duitku', $response->getGateway());
    }

    /**
     * It stops fallback once a payable transaction exists.
     *
     * @return void
     */
    public function testNoFallbackWhenGatewayReturnsPayableTransaction()
    {
        $payment = PaymentOrchestrator::make($this->config());
        $payment->extend('midtrans', function () {
            return new FakeGateway('midtrans', new PaymentResponse(array(
                'success' => true,
                'gateway' => 'midtrans',
                'order_id' => 'INV-001',
                'status' => PaymentStatus::PENDING,
                'payment_url' => 'https://pay.example/INV-001',
            )));
        });

        $response = $payment->create($this->payload());

        $this->assertSame('midtrans', $response->getGateway());
        $this->assertSame('https://pay.example/INV-001', $response->getPaymentUrl());
    }

    /**
     * It returns the existing active attempt for the same order.
     *
     * @return void
     */
    public function testNoDuplicateActiveAttemptForSameOrder()
    {
        $store = new InMemoryPaymentStore();
        $payment = PaymentOrchestrator::make($this->config())->setStore($store);
        $payment->extend('midtrans', function () {
            return new FakeGateway('midtrans');
        });

        $first = $payment->create($this->payload());
        $second = $payment->create($this->payload());

        $this->assertSame($first->getPaymentUrl(), $second->getPaymentUrl());
        $this->assertCount(1, $store->allAttempts());
    }

    /**
     * It blocks fallback for unsafe validation failures.
     *
     * @return void
     */
    public function testFallbackBlockedForValidationFailure()
    {
        $this->expectException(FallbackNotAllowedException::class);

        $payment = PaymentOrchestrator::make($this->config());
        $payment->extend('midtrans', function () {
            return new FakeGateway('midtrans', new PaymentResponse(array(
                'success' => false,
                'gateway' => 'midtrans',
                'order_id' => 'INV-001',
                'message' => 'Invalid amount.',
                'failure_reason' => GatewayFailureReason::VALIDATION_ERROR,
                'fallback_allowed' => false,
            )));
        });

        $payment->create($this->payload());
    }

    /**
     * It raises the callback signature exception on invalid callbacks.
     *
     * @return void
     */
    public function testCallbackSignatureValidation()
    {
        $this->expectException(InvalidCallbackSignatureException::class);

        $payment = PaymentOrchestrator::make($this->config());
        $payment->extend('midtrans', function () {
            return new FakeGateway('midtrans', null, true, false);
        });

        $payment->handleCallback('midtrans', array('order_id' => 'INV-001'));
    }

    /**
     * It maps gateway-specific statuses to universal statuses.
     *
     * @return void
     */
    public function testStatusMapping()
    {
        $mapper = new StatusMapper();

        $this->assertSame(PaymentStatus::PAID, $mapper->mapMidtrans('settlement'));
        $this->assertSame(PaymentStatus::PENDING, $mapper->mapDuitku('01'));
        $this->assertSame(PaymentStatus::FAILED, $mapper->mapXendit('UNKNOWN'));
    }

    /**
     * Return shared fake gateway configuration for tests.
     *
     * @return array Test configuration.
     */
    private function config()
    {
        return array(
            'default' => 'midtrans',
            'fallbacks' => array(
                'midtrans' => array('duitku'),
                'duitku' => array('midtrans'),
            ),
            'gateways' => array(
                'midtrans' => array('driver' => FakeGateway::class),
                'duitku' => array('driver' => FakeGateway::class),
            ),
            'fallback' => array(
                'enabled' => true,
                'max_attempts' => 2,
            ),
        );
    }

    /**
     * Return a valid payment payload with optional overrides.
     *
     * @param array $overrides Payload overrides.
     * @return array Payment payload.
     */
    private function payload(array $overrides = array())
    {
        return array_merge(array(
            'order_id' => 'INV-001',
            'amount' => 150000,
            'customer_name' => 'Mhmfajar',
            'customer_email' => 'mhmfajar@example.com',
        ), $overrides);
    }
}
