<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function client_ip(): string { return (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'); }
function require_same_origin(): void {
    $origin=(string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin==='') return;
    $allowed=['https://kupsiapku.cz','https://www.kupsiapku.cz'];
    if (!in_array($origin,$allowed,true)) respond(['ok'=>false,'error'=>'INVALID_ORIGIN','message'=>'Neplatný původ požadavku.'],403);
}
function rate_key(string $scope,string $value): string { return hash('sha256',$scope.'|'.$value); }
function ensure_rate_table(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS auth_rate_limits (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,action VARCHAR(40) NOT NULL,identifier_hash CHAR(64) NOT NULL,attempts INT UNSIGNED NOT NULL DEFAULT 0,window_started_at DATETIME NOT NULL,blocked_until DATETIME NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_auth_rate_limit(action,identifier_hash),KEY idx_auth_rate_blocked(blocked_until)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}
function rate_limit_check(PDO $pdo,string $action,string $identifier,int $max,int $window,int $block): void {
    ensure_rate_table($pdo); $key=rate_key($action,$identifier);
    $s=$pdo->prepare('SELECT * FROM auth_rate_limits WHERE action=? AND identifier_hash=? LIMIT 1');$s->execute([$action,$key]);$r=$s->fetch(); if(!$r)return;
    $now=time(); $blocked=!empty($r['blocked_until'])?strtotime($r['blocked_until']):false;
    if($blocked!==false && $blocked>$now){$retry=max(1,$blocked-$now);header('Retry-After: '.$retry);respond(['ok'=>false,'error'=>'RATE_LIMITED','message'=>'Příliš mnoho pokusů. Zkuste to později.','retryAfter'=>$retry],429);}
    $start=strtotime((string)$r['window_started_at']); if($start===false || $now-$start>$window)return;
    if((int)$r['attempts'] >= $max){$until=gmdate('Y-m-d H:i:s',$now+$block);$pdo->prepare('UPDATE auth_rate_limits SET blocked_until=? WHERE id=?')->execute([$until,$r['id']]);header('Retry-After: '.$block);respond(['ok'=>false,'error'=>'RATE_LIMITED','message'=>'Příliš mnoho pokusů. Zkuste to později.','retryAfter'=>$block],429);}
}
function rate_limit_hit(PDO $pdo,string $action,string $identifier,int $window): void {
    ensure_rate_table($pdo);$key=rate_key($action,$identifier);
    $s=$pdo->prepare('SELECT * FROM auth_rate_limits WHERE action=? AND identifier_hash=? LIMIT 1');$s->execute([$action,$key]);$r=$s->fetch();
    if(!$r){$pdo->prepare('INSERT INTO auth_rate_limits(action,identifier_hash,attempts,window_started_at) VALUES(?,?,1,UTC_TIMESTAMP())')->execute([$action,$key]);return;}
    $start=strtotime((string)$r['window_started_at']);
    if($start===false || time()-$start>$window)$pdo->prepare('UPDATE auth_rate_limits SET attempts=1,window_started_at=UTC_TIMESTAMP(),blocked_until=NULL WHERE id=?')->execute([$r['id']]);
    else $pdo->prepare('UPDATE auth_rate_limits SET attempts=attempts+1 WHERE id=?')->execute([$r['id']]);
}
function rate_limit_reset(PDO $pdo,string $action,string $identifier): void { ensure_rate_table($pdo);$pdo->prepare('UPDATE auth_rate_limits SET attempts=0,blocked_until=NULL,window_started_at=UTC_TIMESTAMP() WHERE action=? AND identifier_hash=?')->execute([$action,rate_key($action,$identifier)]); }
