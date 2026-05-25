<?php

namespace Mhmfajar\PaymentOrchestrator\Tests;

use Mhmfajar\PaymentOrchestrator\Constants\PaymentStatus;
use Mhmfajar\PaymentOrchestrator\DTO\PaymentRequest;
use Mhmfajar\PaymentOrchestrator\Gateways\DokuGateway;
use Mhmfajar\PaymentOrchestrator\Gateways\DuitkuGateway;
use Mhmfajar\PaymentOrchestrator\Gateways\MidtransGateway;
use Mhmfajar\PaymentOrchestrator\Gateways\XenditGateway;
use PHPUnit\Framework\TestCase;

/**
 * Verifies built-in gateway drivers without making real network calls.
 */
class GatewayDriversTest extends TestCase
{
    /**
     * Midtrans creates a Snap transaction and normalizes the redirect URL.
     *
     * @return void
     */
    public function testMidtransCreatesSnapPayment()
    {
        $captured = array();
        $gateway = new MidtransGateway(array(
            'server_key' => 'server-key',
            'base_url' => 'https://midtrans.example',
            'http_client' => function ($method, $url, $body, $headers) use (&$captured) {
                $captured = compact('method', 'url', 'body', 'headers');

                return array(
                    'status_code' => 201,
                    'body' => json_encode(array('token' => 'snap-token', 'redirect_url' => 'https://snap.example/pay')),
                );
            },
        ));

        $response = $gateway->createPayment($this->request());

        $this->assertTrue($response->isSuccess());
        $this->assertSame('https://snap.example/pay', $response->getPaymentUrl());
        $this->assertSame('snap-token', $response->getTransactionId());
        $this->assertSame('POST', $captured['method']);
        $this->assertSame('https://midtrans.example/snap/v1/transactions', $captured['url']);
        $this->assertContains('Authorization: Basic ' . base64_encode('server-key:'), $captured['headers']);
    }

    /**
     * Duitku signs and creates a transaction inquiry.
     *
     * @return void
     */
    public function testDuitkuCreatesInquiryPayment()
    {
        $captured = array();
        $gateway = new DuitkuGateway(array(
            'merchant_code' => 'D123',
            'api_key' => 'duitku-key',
            'base_url' => 'https://duitku.example',
            'callback_url' => 'https://merchant.example/callback',
            'return_url' => 'https://merchant.example/return',
            'http_client' => function ($method, $url, $body) use (&$captured) {
                $captured = compact('method', 'url', 'body');

                return array(
                    'status_code' => 200,
                    'body' => json_encode(array(
                        'reference' => 'DREF',
                        'paymentUrl' => 'https://duitku.example/pay',
                        'statusCode' => '00',
                        'statusMessage' => 'SUCCESS',
                    )),
                );
            },
        ));

        $response = $gateway->createPayment($this->request());
        $payload = json_decode($captured['body'], true);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('https://duitku.example/pay', $response->getPaymentUrl());
        $this->assertSame('DREF', $response->getGatewayOrderId());
        $this->assertSame(hash_hmac('sha256', 'D123INV-001150000', 'duitku-key'), $payload['signature']);
        $this->assertSame('https://duitku.example/webapi/api/merchant/v2/inquiry', $captured['url']);
    }

    /**
     * Xendit creates an invoice and verifies callback tokens.
     *
     * @return void
     */
    public function testXenditCreatesInvoiceAndVerifiesCallback()
    {
        $captured = array();
        $gateway = new XenditGateway(array(
            'secret_key' => 'xendit-secret',
            'callback_token' => 'xendit-callback-token',
            'base_url' => 'https://xendit.example',
            'http_client' => function ($method, $url, $body, $headers) use (&$captured) {
                $captured = compact('method', 'url', 'body', 'headers');

                return array(
                    'status_code' => 200,
                    'body' => json_encode(array(
                        'id' => 'inv_123',
                        'external_id' => 'INV-001',
                        'status' => 'PENDING',
                        'invoice_url' => 'https://checkout.xendit.co/inv_123',
                    )),
                );
            },
        ));

        $response = $gateway->createPayment($this->request());
        $callback = $gateway->handleCallback(array(
            '_headers' => array('x-callback-token' => 'xendit-callback-token'),
            'id' => 'inv_123',
            'external_id' => 'INV-001',
            'status' => 'PAID',
        ));

        $this->assertTrue($response->isSuccess());
        $this->assertSame('https://checkout.xendit.co/inv_123', $response->getPaymentUrl());
        $this->assertContains('Authorization: Basic ' . base64_encode('xendit-secret:'), $captured['headers']);
        $this->assertTrue($callback->isValid());
        $this->assertSame(PaymentStatus::PAID, $callback->getStatus());
    }

    /**
     * DOKU signs Checkout requests and normalizes the payment URL.
     *
     * @return void
     */
    public function testDokuCreatesCheckoutPayment()
    {
        $captured = array();
        $gateway = new DokuGateway(array(
            'client_id' => 'MCH-001',
            'secret_key' => 'doku-secret',
            'base_url' => 'https://doku.example',
            'http_client' => function ($method, $url, $body, $headers) use (&$captured) {
                $captured = compact('method', 'url', 'body', 'headers');

                return array(
                    'status_code' => 200,
                    'body' => json_encode(array(
                        'response' => array(
                            'order' => array('invoice_number' => 'INV-001'),
                            'payment' => array('url' => 'https://checkout.doku.com/pay'),
                        ),
                    )),
                );
            },
        ));

        $response = $gateway->createPayment($this->request());
        $payload = json_decode($captured['body'], true);

        $this->assertTrue($response->isSuccess());
        $this->assertSame('https://checkout.doku.com/pay', $response->getPaymentUrl());
        $this->assertSame('INV-001', $payload['order']['invoice_number']);
        $this->assertContains('Client-Id: MCH-001', $captured['headers']);
        $this->assertStringStartsWith('Signature: HMACSHA256=', $this->headerStartingWith($captured['headers'], 'Signature:'));
    }

    /**
     * Build a reusable payment request.
     *
     * @return PaymentRequest
     */
    private function request()
    {
        return new PaymentRequest(
            'INV-001',
            150000,
            'Mhmfajar',
            'mhmfajar@example.com',
            '08123456789',
            'IDR',
            array(array('name' => 'Test Product', 'price' => 150000, 'quantity' => 1)),
            array(),
            null,
            'Test payment',
            'https://merchant.example/callback',
            'https://merchant.example/return'
        );
    }

    /**
     * Find the first header with a given prefix.
     *
     * @param array $headers Headers.
     * @param string $prefix Prefix.
     * @return string
     */
    private function headerStartingWith(array $headers, $prefix)
    {
        foreach ($headers as $header) {
            if (strpos($header, $prefix) === 0) {
                return $header;
            }
        }

        return '';
    }
}
