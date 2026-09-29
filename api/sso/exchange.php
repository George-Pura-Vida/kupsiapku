<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/checkout.php';
require_once __DIR__.'/schema.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);}
$body=file_get_contents('php://input')?:'';$ts=(string)($_SERVER['HTTP_X_SSO_TIMESTAMP']??'');$nonce=(string)($_SERVER['HTTP_X_SSO_NONCE']??'');$sig=(string)($_SERVER['HTTP_X_SSO_SIGNATURE']??'');$secret=(string)getenv('PRIORITY_SSO_SECRET');
if(strlen($secret)<32)respond(['ok'=>false,'error'=>'SSO_CONFIG_ERROR'],500);
if(!ctype_digit($ts)||abs(time()-(int)$ts)>60||!preg_match('/^[A-Za-z0-9_-]{20,100}$/',$nonce)||!preg_match('/^[a-f0-9]{64}$/i',$sig))respond(['ok'=>false,'error'=>'INVALID_SSO_REQUEST'],401);
if(!hash_equals(hash_hmac('sha256',$ts."\n".$nonce."\n".$body,$secret),$sig))respond(['ok'=>false,'error'=>'INVALID_SIGNATURE'],401);
try{$data=json_decode($body,true,512,JSON_THROW_ON_ERROR);}catch(Throwable $e){respond(['ok'=>false,'error'=>'INVALID_JSON'],400);}
$ticket=(string)($data['ticket']??'');if(($data['app']??'')!=='cile'||!preg_match('/^[A-Za-z0-9_-]{40,100}$/',$ticket))respond(['ok'=>false,'error'=>'INVALID_REQUEST'],400);
$pdo=db();ensure_auth_schema($pdo);ensure_checkout_schema($pdo);ensure_sso_schema($pdo);
try{$pdo->beginTransaction();
 try{$pdo->prepare('INSERT INTO sso_request_nonces(nonce_hash,app_code,expires_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 MINUTE))')->execute([hash('sha256',$nonce),'cile']);}catch(PDOException $e){if((int)($e->errorInfo[1]??0)===1062){$pdo->rollBack();respond(['ok'=>false,'error'=>'SSO_REPLAY_DETECTED'],409);}throw $e;}
 $q=$pdo->prepare('SELECT id,user_id,app_code,expires_at,used_at FROM sso_tickets WHERE token_hash=? LIMIT 1 FOR UPDATE');$q->execute([hash('sha256',$ticket)]);$row=$q->fetch();
 if(!$row||$row['app_code']!=='cile'||$row['used_at']!==null||strtotime($row['expires_at'].' UTC')<=time()){$pdo->rollBack();respond(['ok'=>false,'error'=>'INVALID_TICKET'],401);}
 if(!has_active_product($pdo,(int)$row['user_id'],'cile')){$pdo->rollBack();respond(['ok'=>false,'error'=>'LICENSE_REQUIRED'],403);}
 $q=$pdo->prepare('SELECT id,email FROM users WHERE id=? LIMIT 1');$q->execute([(int)$row['user_id']]);$user=$q->fetch();if(!$user){$pdo->rollBack();respond(['ok'=>false,'error'=>'USER_NOT_FOUND'],403);}
 $u=$pdo->prepare('UPDATE sso_tickets SET used_at=UTC_TIMESTAMP() WHERE id=? AND used_at IS NULL');$u->execute([(int)$row['id']]);if($u->rowCount()!==1){$pdo->rollBack();respond(['ok'=>false,'error'=>'TICKET_ALREADY_USED'],409);}
 $pdo->commit();respond(['ok'=>true,'user'=>['id'=>(int)$user['id'],'email'=>(string)$user['email']],'license'=>['app'=>'cile','status'=>'active']]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('[priority-sso] exchange failed: '.$e->getMessage());respond(['ok'=>false,'error'=>'SSO_FAILED'],500);}
