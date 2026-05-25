<?php

// Framework-agnostic default configuration for native PHP and adapter packages.
return array(
    'default' => getenv('PAYMENT_GATEWAY') ?: 'midtrans',
    'fallbacks' => array(
        'midtrans' => array('duitku', 'xendit', 'doku'),
        'duitku' => array('midtrans', 'xendit', 'doku'),
        'xendit' => array('midtrans', 'duitku', 'doku'),
        'doku' => array('midtrans', 'duitku', 'xendit'),
    ),
    'gateways' => array(
        'midtrans' => array(
            'driver' => \Mhmfajar\PaymentOrchestrator\Gateways\MidtransGateway::class,
            'server_key' => getenv('MIDTRANS_SERVER_KEY'),
            'client_key' => getenv('MIDTRANS_CLIENT_KEY'),
            'merchant_id' => getenv('MIDTRANS_MERCHANT_ID'),
            'is_production' => filter_var(getenv('MIDTRANS_PRODUCTION'), FILTER_VALIDATE_BOOLEAN),
            'callback_url' => getenv('MIDTRANS_CALLBACK_URL'),
        ),
        'duitku' => array(
            'driver' => \Mhmfajar\PaymentOrchestrator\Gateways\DuitkuGateway::class,
            'merchant_code' => getenv('DUITKU_MERCHANT_CODE'),
            'api_key' => getenv('DUITKU_API_KEY'),
            'is_production' => filter_var(getenv('DUITKU_PRODUCTION'), FILTER_VALIDATE_BOOLEAN),
            'callback_url' => getenv('DUITKU_CALLBACK_URL'),
            'return_url' => getenv('DUITKU_RETURN_URL'),
        ),
        'xendit' => array(
            'driver' => \Mhmfajar\PaymentOrchestrator\Gateways\XenditGateway::class,
            'secret_key' => getenv('XENDIT_SECRET_KEY'),
            'callback_token' => getenv('XENDIT_CALLBACK_TOKEN'),
            'is_production' => filter_var(getenv('XENDIT_PRODUCTION'), FILTER_VALIDATE_BOOLEAN),
            'callback_url' => getenv('XENDIT_CALLBACK_URL'),
        ),
        'doku' => array(
            'driver' => \Mhmfajar\PaymentOrchestrator\Gateways\DokuGateway::class,
            'client_id' => getenv('DOKU_CLIENT_ID'),
            'secret_key' => getenv('DOKU_SECRET_KEY'),
            'is_production' => filter_var(getenv('DOKU_PRODUCTION'), FILTER_VALIDATE_BOOLEAN),
            'callback_url' => getenv('DOKU_CALLBACK_URL'),
        ),
    ),
    'fallback' => array(
        'enabled' => true,
        'max_attempts' => 3,
        'allowed_failure_reasons' => array(
            'connection_timeout',
            'gateway_unavailable',
            'server_error',
            'invalid_gateway_response',
            'health_check_failed',
        ),
        'blocked_statuses' => array('pending', 'paid', 'expired', 'cancelled', 'refunded'),
    ),
    'tables' => array(
        'payments' => 'payments',
        'payment_attempts' => 'payment_attempts',
    ),
);
