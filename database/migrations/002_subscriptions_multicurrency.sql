-- KUP SI APKU - subscriptions and multi-currency billing
-- Monetary amounts are stored in minor units. FX equivalents must be calculated server-side at checkout.
CREATE TABLE IF NOT EXISTS subscriptions(
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 provider ENUM('gopay') NOT NULL DEFAULT 'gopay',
 provider_subscription_reference VARCHAR(190) UNIQUE,
 base_amount_minor BIGINT UNSIGNED NOT NULL DEFAULT 19900,
 base_currency CHAR(3) NOT NULL DEFAULT 'CZK',
 billing_currency ENUM('CZK','EUR','USD') NOT NULL DEFAULT 'CZK',
 interval_unit ENUM('month') NOT NULL DEFAULT 'month',
 status ENUM('pending','active','past_due','cancelled','ended') NOT NULL DEFAULT 'pending',
 current_period_start DATETIME,
 current_period_end DATETIME,
 cancelled_at DATETIME,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_subscription_user_status(user_id,status),
 CONSTRAINT fk_subscription_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE payments
 ADD COLUMN payment_kind ENUM('one_time','recurring') NOT NULL DEFAULT 'one_time' AFTER method,
 ADD COLUMN subscription_id BIGINT UNSIGNED NULL AFTER order_id,
 ADD KEY idx_payment_subscription(subscription_id),
 ADD CONSTRAINT fk_payment_subscription FOREIGN KEY(subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL;

-- Security rule: never trust currency, amount or recurring status sent by the browser.
-- The production API must calculate the final amount server-side, create the GoPay payment,
-- verify provider callbacks/webhooks and only then activate purchased apps/subscriptions.
