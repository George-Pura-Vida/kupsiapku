<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/security.php';
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { header('Allow: POST'); respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405); }
require_same_origin();
$pdo=db(true); ensure_auth_schema($pdo); $data=json_input();
$ip=client_ip(); rate_limit_check($pdo,'reset_ip',$ip,20,900,900); rate_limit_hit($pdo,'reset_ip',$ip,900);
$token=(string)($data['token']??''); $password=(string)($data['newPassword']??'');
if (strlen($password)<8 || strlen($password)>72 || str_contains($password,"\0")) respond(['ok'=>false,'error'=>'INVALID_PASSWORD','message'=>'Heslo musí mít 8 až 72 znaků.'],422);
if ($password!==(string)($data['confirmPassword']??'')) respond(['ok'=>false,'error'=>'PASSWORD_MISMATCH','message'=>'Hesla se neshodují.'],422);
if (!preg_match('/^[a-f0-9]{64}$/D',$token)) respond(['ok'=>false,'error'=>'INVALID_TOKEN','message'=>'Odkaz je neplatný nebo vypršel.'],422);
$digest=hash('sha256',$token); $hash=password_hash($password,PASSWORD_DEFAULT);
$pdo->beginTransaction();
try {
    $q=$pdo->prepare('SELECT user_id FROM auth_password_resets WHERE token_hash=? AND expires_at>UTC_TIMESTAMP() FOR UPDATE'); $q->execute([$digest]); $userId=$q->fetchColumn();
    if (!$userId) { $pdo->rollBack(); respond(['ok'=>false,'error'=>'INVALID_TOKEN','message'=>'Odkaz je neplatný nebo vypršel.'],422); }
    $q=$pdo->prepare('UPDATE users SET password_hash=? WHERE id=? AND status="active"'); $q->execute([$hash,$userId]);
    if ($q->rowCount()!==1) { $pdo->rollBack(); respond(['ok'=>false,'error'=>'INVALID_TOKEN','message'=>'Odkaz je neplatný nebo vypršel.'],422); }
    $pdo->prepare('DELETE FROM auth_password_resets WHERE user_id=?')->execute([$userId]);
    $pdo->prepare('UPDATE auth_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND revoked_at IS NULL')->execute([$userId]);
    $pdo->commit();
} catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
clear_auth_cookie(); respond(['ok'=>true,'message'=>'Heslo bylo změněno. Přihlaste se novým heslem.']);
