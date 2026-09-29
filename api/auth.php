<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
const AUTH_COOKIE='ksa_session';
function auth_lifetime(): int{return 2592000;}
function set_auth_cookie(string $token): void{setcookie(AUTH_COOKIE,$token,['expires'=>time()+auth_lifetime(),'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);}
function clear_auth_cookie(): void{setcookie(AUTH_COOKIE,'',['expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);}
function ensure_auth_schema(PDO $pdo): void{
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,email VARCHAR(190) NOT NULL UNIQUE,password_hash VARCHAR(255) NULL,first_name VARCHAR(100) NULL,last_name VARCHAR(100) NULL,phone VARCHAR(40) NULL,role VARCHAR(30) NOT NULL DEFAULT "customer",status VARCHAR(30) NOT NULL DEFAULT "active",created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec('CREATE TABLE IF NOT EXISTS auth_sessions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,csrf_hash CHAR(64) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,expires_at DATETIME NOT NULL,revoked_at DATETIME NULL,KEY idx_auth_user(user_id),KEY idx_auth_expires(expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec('CREATE TABLE IF NOT EXISTS auth_password_resets (token_hash CHAR(64) PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,expires_at DATETIME NOT NULL,KEY idx_auth_reset_user(user_id),KEY idx_auth_reset_expiry(expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}
function auth_token(): ?string{$t=$_COOKIE[AUTH_COOKIE]??null;return is_string($t)&&preg_match('/^[a-f0-9]{64}$/',$t)?$t:null;}
function create_auth_session(PDO $pdo,int $userId): string{$token=bin2hex(random_bytes(32));$csrf=bin2hex(random_bytes(32));$s=$pdo->prepare('INSERT INTO auth_sessions(user_id,token_hash,csrf_hash,created_at,expires_at) VALUES(?,?,?,UTC_TIMESTAMP(),?)');$s->execute([$userId,hash('sha256',$token),hash('sha256',$csrf),gmdate('Y-m-d H:i:s',time()+auth_lifetime())]);set_auth_cookie($token);return $csrf;}
function current_session(PDO $pdo): ?array{ensure_auth_schema($pdo);$t=auth_token();if($t===null)return null;$s=$pdo->prepare('SELECT s.id session_id,s.csrf_hash,u.id,u.email,u.first_name,u.last_name,u.phone,u.role,u.status FROM auth_sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.revoked_at IS NULL AND s.expires_at>UTC_TIMESTAMP() AND u.status="active" LIMIT 1');$s->execute([hash('sha256',$t)]);return $s->fetch()?:null;}
function require_user(PDO $pdo): array{$u=current_session($pdo);if(!$u)respond(['ok'=>false,'error'=>'AUTH_REQUIRED','message'=>'Pro tuto operaci se přihlaste.'],401);return $u;}
function require_csrf(PDO $pdo): array{$s=require_user($pdo);$t=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');if(!preg_match('/^[a-f0-9]{64}$/',$t)||empty($s['csrf_hash'])||!hash_equals((string)$s['csrf_hash'],hash('sha256',$t)))respond(['ok'=>false,'error'=>'CSRF_FAILED','message'=>'Bezpečnostní token není platný.'],403);return $s;}
function rotate_csrf(PDO $pdo,int $sessionId): string{$t=bin2hex(random_bytes(32));$pdo->prepare('UPDATE auth_sessions SET csrf_hash=? WHERE id=?')->execute([hash('sha256',$t),$sessionId]);return $t;}
function revoke_current_session(PDO $pdo): void{$t=auth_token();if($t!==null)$pdo->prepare('UPDATE auth_sessions SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=? AND revoked_at IS NULL')->execute([hash('sha256',$t)]);clear_auth_cookie();}
