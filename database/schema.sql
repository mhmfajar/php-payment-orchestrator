-- Default SQL schema for native PHP integrations using the PDO payment store.
CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(191) NOT NULL UNIQUE,
    amount BIGINT UNSIGNED NOT NULL,
    currency VARCHAR(10) DEFAULT 'IDR',
    status VARCHAR(50) DEFAULT 'pending',
    active_gateway VARCHAR(100) NULL,
    customer_name VARCHAR(191) NULL,
    customer_email VARCHAR(191) NULL,
    customer_phone VARCHAR(50) NULL,
    items JSON NULL,
    metadata JSON NULL,
    paid_at TIMESTAMP NULL,
    expired_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE payment_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    gateway VARCHAR(100) NOT NULL,
    gateway_order_id VARCHAR(191) NULL,
    gateway_transaction_id VARCHAR(191) NULL,
    status VARCHAR(50) DEFAULT 'pending',
    payment_url TEXT NULL,
    qr_string TEXT NULL,
    va_number VARCHAR(191) NULL,
    error_code VARCHAR(191) NULL,
    error_message TEXT NULL,
    failure_reason VARCHAR(191) NULL,
    is_active BOOLEAN DEFAULT FALSE,
    raw_request JSON NULL,
    raw_response JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX payment_attempts_payment_gateway_index (payment_id, gateway),
    INDEX payment_attempts_gateway_order_index (gateway, gateway_order_id),
    INDEX payment_attempts_status_index (status),
    CONSTRAINT payment_attempts_payment_id_foreign FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);
