<?php
declare(strict_types=1);

function ensure_sso_schema(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS sso_tickets (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,token_hash CHAR(64) NOT NULL UNIQUE,user_id BIGINT UNSIGNED NOT NULL,app_code VARCHAR(50) NOT NULL,expires_at DATETIME NOT NULL,used_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_sso_ticket_user(user_id),KEY idx_sso_ticket_expiry(expires_at),KEY idx_sso_ticket_app_user(app_code,user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec('CREATE TABLE IF NOT EXISTS sso_request_nonces (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,nonce_hash CHAR(64) NOT NULL UNIQUE,app_code VARCHAR(50) NOT NULL,expires_at DATETIME NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_sso_nonce_expiry(expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}
