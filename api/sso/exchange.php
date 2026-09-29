<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/checkout.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST') respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);
$body=file_get_contents('php://input')?:'';
$timestamp=(string)($_SERVER['HTTP_X_SSO_TIMESTAMP']??'');
$nonce=(string)($_SERVER['HTTP_X_SSO_NONCE']??'');
$signature=(string)($_SERVER['HTTP_X_SSO_SIGNATURE']??'');
$secret=(string)getenv('PRIORITY_SSO_SECRET');
if($secret===''||strlen($secret)<32) respond(['ok'=>false,'error'=>'SSO_CONFIG_ERROR'],500);
if(!ctype_digit($timestamp)||abs(time()-(int)$timestamp)>60||!preg_match('/^[A-Za-z0-9_-]{20,100}$/',$nonce)||!preg_match('/^[a-f0-9]{64}$/i',$signature)) respond(['ok'=>false,'error'=>'INVALID_SSO_REQUEST'],401);
$expected=hash_hmac('sha256',$timestamp."\n".$nonce."\n".$body,$secret);
if(!hash_equals($expected,$signature)) respond(['ok'=>false,'error'=>'INVALID_SIGNATURE'],401);
$data=json_decode($body,true);
$ticket=is_array($data)?(string)($data['ticket']??''):'';
$app=is_array($data)?(string)($data['app']??''):'';
if($ticket===''||strlen($ticket)>200||$app!=='cile') respond(['ok'=>false,'error'=>'INVALID_REQUEST'],400);
$pdo=db(true); ensure_auth_schema($pdo); ensure_checkout_schema($pdo);
try{
 $pdo->beginTransaction();
 try{$pdo->prepare('INSERT INTO sso_request_nonces(nonce_hash,app_code,expires_at) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 MINUTE))')->execute([hash('sha256',$nonce),'cile']);}
 catch(PDOException $e){if((int)($e->errorInfo[1]??0)===1062){$pdo->rollBack();respond(['ok'=>false,'error'=>'SSO_REPLAY_DETECTED'],409);}throw $e;}
 $q=$pdo->prepare('SELECT id,user_id,app_code,expires_at,used_at FROM sso_tickets WHERE token_hash=? LIMIT 1 FOR UPDATE');
 $q->execute([hash('sha256',$ticket)]);$row=$q->fetch();
 if(!$row||$row['app_code']!=='cile'||$row['used_at']!==null||strtotime($row['expires_at'].' UTC')<=time()){$pdo->rollBack();respond(['ok'=>false,'error'=>'INVALID_TICKET'],401);}
 if(!has_active_product($pdo,(int)$row['user_id'],'cile')){$pdo->rollBack();respond(['ok'=>false,'error'=>'LICENSE_REQUIRED'],403);}
 $q=$pdo->prepare('SELECT id,email,status FROM users WHERE id=? LIMIT 1');$q->execute([(int)$row['user_id']]);$user=$q->fetch();
 if(!$user||$user['status']!=='active'){$pdo->rollBack();respond(['ok'=>false,'error'=>'USER_UNAVAILABLE'],403);}
 $u=$pdo->prepare('UPDATE sso_tickets SET used_at=UTC_TIMESTAMP() WHERE id=? AND used_at IS NULL');$u->execute([(int)$row['id']]);
 if($u->rowCount()!==1){$pdo->rollBack();respond(['ok'=>false,'error'=>'TICKET_ALREADY_USED'],409);}
 $pdo->commit();
 respond(['ok'=>true,'user'=>['id'=>(int)$user['id'],'email'=>(string)$user['email']],'license'=>['app'=>'cile','status'=>'active']]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('[sso/exchange] '.$e->getMessage());respond(['ok'=>false,'error'=>'SSO_FAILED'],500);}
