<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function load_local_env(): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    $file = dirname(__DIR__) . '/.env';
    if (!is_file($file) || !is_readable($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key,$value] = array_map('trim', explode('=', $line, 2));
        if ($key !== '' && getenv($key) === false) putenv($key.'='.trim($value, "\"'"));
    }
}
load_local_env();

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

function create_sqlite_schema(PDO $pdo): void {
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE IF NOT EXISTS affiliates (id INTEGER PRIMARY KEY AUTOINCREMENT,code TEXT NOT NULL UNIQUE,access_token_hash TEXT NOT NULL UNIQUE,first_name TEXT NOT NULL,last_name TEXT NOT NULL,email TEXT NOT NULL UNIQUE,phone TEXT NOT NULL,street TEXT NOT NULL,zip TEXT NOT NULL,city TEXT NOT NULL,country TEXT NOT NULL,created_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS referral_clicks (id INTEGER PRIMARY KEY AUTOINCREMENT,affiliate_id INTEGER NOT NULL,visitor_hash TEXT NOT NULL,landing_page TEXT,created_at TEXT NOT NULL,UNIQUE(affiliate_id, visitor_hash),FOREIGN KEY(affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS referrals (id INTEGER PRIMARY KEY AUTOINCREMENT,affiliate_id INTEGER NOT NULL,order_reference TEXT NOT NULL UNIQUE,amount_minor INTEGER NOT NULL DEFAULT 0,commission_minor INTEGER NOT NULL DEFAULT 0,status TEXT NOT NULL DEFAULT "pending",created_at TEXT NOT NULL,FOREIGN KEY(affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS donations (id INTEGER PRIMARY KEY AUTOINCREMENT,public_id TEXT NOT NULL UNIQUE,amount_minor INTEGER NOT NULL,donor_name TEXT,donor_email TEXT,message TEXT,variable_symbol TEXT NOT NULL UNIQUE,status TEXT NOT NULL DEFAULT "pending",created_at TEXT NOT NULL)');
}

function create_mysql_schema(PDO $pdo): void {
    $queries = [
        'CREATE TABLE IF NOT EXISTS affiliates (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,code VARCHAR(50) NOT NULL UNIQUE,access_token_hash CHAR(64) NOT NULL UNIQUE,first_name VARCHAR(100) NOT NULL,last_name VARCHAR(100) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,phone VARCHAR(40) NOT NULL,street VARCHAR(190) NOT NULL,zip VARCHAR(20) NOT NULL,city VARCHAR(100) NOT NULL,country VARCHAR(100) NOT NULL,created_at VARCHAR(40) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS referral_clicks (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,affiliate_id BIGINT UNSIGNED NOT NULL,visitor_hash CHAR(64) NOT NULL,landing_page VARCHAR(255),created_at VARCHAR(40) NOT NULL,UNIQUE KEY uq_affiliate_visitor(affiliate_id,visitor_hash),CONSTRAINT fk_click_affiliate FOREIGN KEY(affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS referrals (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,affiliate_id BIGINT UNSIGNED NOT NULL,order_reference VARCHAR(100) NOT NULL UNIQUE,amount_minor BIGINT NOT NULL DEFAULT 0,commission_minor BIGINT NOT NULL DEFAULT 0,status VARCHAR(20) NOT NULL DEFAULT "pending",created_at VARCHAR(40) NOT NULL,KEY idx_referrals_affiliate_status(affiliate_id,status),CONSTRAINT fk_referral_affiliate FOREIGN KEY(affiliate_id) REFERENCES affiliates(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS donations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,public_id VARCHAR(30) NOT NULL UNIQUE,amount_minor BIGINT NOT NULL,donor_name VARCHAR(190),donor_email VARCHAR(190),message TEXT,variable_symbol VARCHAR(20) NOT NULL UNIQUE,status VARCHAR(20) NOT NULL DEFAULT "pending",created_at VARCHAR(40) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS payout_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,affiliate_id BIGINT UNSIGNED NOT NULL,amount_minor BIGINT NOT NULL,currency CHAR(3) NOT NULL DEFAULT "CZK",status VARCHAR(20) NOT NULL DEFAULT "requested",requested_at VARCHAR(40) NOT NULL,approved_at VARCHAR(40),paid_at VARCHAR(40),CONSTRAINT fk_payout_affiliate FOREIGN KEY(affiliate_id) REFERENCES affiliates(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        'CREATE TABLE IF NOT EXISTS invoices (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,payout_request_id BIGINT UNSIGNED NOT NULL UNIQUE,invoice_number VARCHAR(40) NOT NULL UNIQUE,pdf_path VARCHAR(255),status VARCHAR(20) NOT NULL DEFAULT "created",created_at VARCHAR(40) NOT NULL,emailed_at VARCHAR(40),CONSTRAINT fk_invoice_payout FOREIGN KEY(payout_request_id) REFERENCES payout_requests(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    ];
    foreach ($queries as $sql) $pdo->exec($sql);
    $columns = [
        'ico VARCHAR(20) NULL','dic VARCHAR(30) NULL','vat_payer TINYINT(1) NOT NULL DEFAULT 0',
        'bank_account VARCHAR(60) NULL','iban VARCHAR(50) NULL','bic VARCHAR(20) NULL'
    ];
    foreach ($columns as $column) {
        try {$pdo->exec('ALTER TABLE affiliates ADD COLUMN '.$column);} catch (Throwable $ignored) {}
    }
}

function migrate_sqlite_to_mysql(PDO $mysql): void {
    $sqliteFile = __DIR__ . '/data/kupsiapku.sqlite';
    if (!is_file($sqliteFile) || (int)$mysql->query('SELECT COUNT(*) FROM affiliates')->fetchColumn() > 0) return;
    try {
        $sqlite = new PDO('sqlite:'.$sqliteFile, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $mysql->beginTransaction();
        foreach (['affiliates','referral_clicks','referrals','donations'] as $table) {
            $rows = $sqlite->query('SELECT * FROM '.$table)->fetchAll();
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $sql = 'INSERT IGNORE INTO '.$table.' ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';
                $mysql->prepare($sql)->execute(array_values($row));
            }
        }
        $mysql->commit();
    } catch (Throwable $error) {
        if ($mysql->inTransaction()) $mysql->rollBack();
        error_log('SQLite to MySQL migration failed: '.$error->getMessage());
    }
}

function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $host = trim((string)getenv('DB_HOST'));
    $name = trim((string)getenv('DB_NAME'));
    $user = trim((string)getenv('DB_USER'));
    $password = (string)getenv('DB_PASSWORD');
    if ($host !== '' && $name !== '' && $user !== '' && $password !== '') {
        try {
            $port = (int)(getenv('DB_PORT') ?: 3306);
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $password, [
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false,
            ]);
            create_mysql_schema($pdo);
            migrate_sqlite_to_mysql($pdo);
            return $pdo;
        } catch (Throwable $error) {
            error_log('MySQL connection failed: '.$error->getMessage());
            $pdo = null;
        }
    }
    $dir = __DIR__ . '/data';
    if (!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir)) respond(['ok'=>false,'error'=>'Databázové úložiště není dostupné.'],503);
    $pdo = new PDO('sqlite:'.$dir.'/kupsiapku.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    create_sqlite_schema($pdo);
    return $pdo;
}

function clean_string(array $data, string $key, int $max = 190): string {
    return mb_substr(trim((string)($data[$key] ?? '')),0,$max);
}
function affiliate_from_token(PDO $pdo,string $token): ?array {
    if ($token === '') return null;
    $stmt=$pdo->prepare('SELECT * FROM affiliates WHERE access_token_hash = ?');
    $stmt->execute([hash('sha256',$token)]);
    return $stmt->fetch() ?: null;
}
function unique_code(PDO $pdo,string $first,string $last): string {
    $ascii=static function(string $value): string {$converted=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value) ?: $value;return substr(preg_replace('/[^A-Z0-9]/','',strtoupper($converted)) ?: 'APKA',0,6);};
    do {$code=$ascii($first).'-'.$ascii($last).'-'.strtoupper(bin2hex(random_bytes(2)));$stmt=$pdo->prepare('SELECT 1 FROM affiliates WHERE code = ?');$stmt->execute([$code]);} while($stmt->fetchColumn());
    return $code;
}
