# PHP Payment Orchestrator Core

<!-- Core package usage notes for native PHP and framework adapter consumers. -->

Framework-agnostic payment gateway orchestration for PHP `^7.3 || ^8.0`.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Mhmfajar\PaymentOrchestrator\PaymentOrchestrator;
use Mhmfajar\PaymentOrchestrator\Storage\PdoPaymentStore;

$config = require __DIR__ . '/config/payment-orchestrator.php';
$pdo = new PDO('mysql:host=localhost;dbname=my_app', 'root', '');

$payment = PaymentOrchestrator::make($config)
    ->setStore(new PdoPaymentStore($pdo, $config['tables']));

$response = $payment->create(array(
    'order_id' => 'INV-001',
    'amount' => 150000,
    'customer_name' => 'Mhmfajar',
    'customer_email' => 'mhmfajar@example.com',
));

header('Location: ' . $response->getPaymentUrl());
```

Built-in gateway drivers are provided for Midtrans Snap, Duitku Inquiry, Xendit Invoices, and DOKU Checkout. Configure the matching gateway credentials before using them in live payment flows.
