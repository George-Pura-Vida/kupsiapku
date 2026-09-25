<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function json_input(): array {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $dir = __DIR__ . '/data';
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        respond(['ok' => false, 'error' => 'Databázové úložiště není dostupné.'], 503);
    }
    $pdo = new PDO('sqlite:' . $dir . '/kupsiapku.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE IF NOT EXISTS affiliates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        access_token_hash TEXT NOT NULL UNIQUE,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        phone TEXT NOT NULL,
        street TEXT NOT NULL,
        zip TEXT NOT NULL,
        city TEXT NOT NULL,
        country TEXT NOT NULL,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS referral_clicks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        affiliate_id INTEGER NOT NULL,
        visitor_hash TEXT NOT NULL,
        landing_page TEXT,
        created_at TEXT NOT NULL,
        UNIQUE(affiliate_id, visitor_hash),
        FOREIGN KEY(affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS referrals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        affiliate_id INTEGER NOT NULL,
        order_reference TEXT NOT NULL UNIQUE,
        amount_minor INTEGER NOT NULL DEFAULT 0,
        commission_minor INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT "pending",
        created_at TEXT NOT NULL,
        FOREIGN KEY(affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE
    )');
    $pdo->exec('CREATE TABLE IF NOT EXISTS donations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT NOT NULL UNIQUE,
        amount_minor INTEGER NOT NULL,
        donor_name TEXT,
        donor_email TEXT,
        message TEXT,
        variable_symbol TEXT NOT NULL UNIQUE,
        status TEXT NOT NULL DEFAULT "pending",
        created_at TEXT NOT NULL
    )');
    return $pdo;
}

function clean_string(array $data, string $key, int $max = 190): string {
    return mb_substr(trim((string)($data[$key] ?? '')), 0, $max);
}

function affiliate_from_token(PDO $pdo, string $token): ?array {
    if ($token === '') return null;
    $stmt = $pdo->prepare('SELECT * FROM affiliates WHERE access_token_hash = ?');
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

function unique_code(PDO $pdo, string $first, string $last): string {
    $ascii = static function(string $value): string {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($converted)) ?: 'APKA', 0, 6);
    };
    do {
        $code = $ascii($first) . '-' . $ascii($last) . '-' . strtoupper(bin2hex(random_bytes(2)));
        $stmt = $pdo->prepare('SELECT 1 FROM affiliates WHERE code = ?');
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn());
    return $code;
}

