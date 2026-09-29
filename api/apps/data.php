<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/checkout.php';

$method=$_SERVER['REQUEST_METHOD']??'GET';
$user=$method==='GET'?require_user($pdo):require_csrf($pdo);
$userId=(int)$user['id'];
ensure_checkout_schema($pdo);
$code=preg_replace('/[^a-z0-9_-]/','',(string)($_GET['app']??''));
$allowed=['zdravi','finance','investice','cile','vztahy','rozvoj','firma','podnikani','prace'];
if(!in_array($code,$allowed,true))respond(['ok'=>false,'error'=>'APP_NOT_FOUND'],404);
if(!has_active_product($pdo,$userId,$code))respond(['ok'=>false,'error'=>'LICENSE_REQUIRED','message'=>'K této aplikaci nemáte aktivní oprávnění.'],403);

$pdo->exec('CREATE TABLE IF NOT EXISTS ksa_app_data (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,product_code VARCHAR(50) NOT NULL,data_key VARCHAR(100) NOT NULL,data_json LONGTEXT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_ksa_app_data(user_id,product_code,data_key),KEY idx_ksa_app_data_scope(user_id,product_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
$key=preg_replace('/[^a-zA-Z0-9_.-]/','',(string)($_GET['key']??'state'));
if($key===''||strlen($key)>100)respond(['ok'=>false,'error'=>'INVALID_KEY'],422);

if($method==='GET'){
 $q=$pdo->prepare('SELECT data_json,updated_at FROM ksa_app_data WHERE user_id=? AND product_code=? AND data_key=? LIMIT 1');
 $q->execute([$userId,$code,$key]);$row=$q->fetch(PDO::FETCH_ASSOC);
 respond(['ok'=>true,'app'=>$code,'key'=>$key,'data'=>$row?json_decode((string)$row['data_json'],true):null,'updatedAt'=>$row['updated_at']??null]);
}
if($method==='PUT'||$method==='POST'){
 $input=json_input();if(!array_key_exists('data',$input))respond(['ok'=>false,'error'=>'DATA_REQUIRED'],422);
 try{$json=json_encode($input['data'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}catch(JsonException $e){respond(['ok'=>false,'error'=>'INVALID_DATA'],422);}
 if(strlen($json)>1048576)respond(['ok'=>false,'error'=>'DATA_TOO_LARGE'],413);
 $q=$pdo->prepare('INSERT INTO ksa_app_data(user_id,product_code,data_key,data_json) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE data_json=VALUES(data_json),updated_at=UTC_TIMESTAMP()');
 $q->execute([$userId,$code,$key,$json]);respond(['ok'=>true,'app'=>$code,'key'=>$key,'saved'=>true]);
}
if($method==='DELETE'){
 $q=$pdo->prepare('DELETE FROM ksa_app_data WHERE user_id=? AND product_code=? AND data_key=?');$q->execute([$userId,$code,$key]);
 respond(['ok'=>true,'app'=>$code,'key'=>$key,'deleted'=>$q->rowCount()>0]);
}
header('Allow: GET, POST, PUT, DELETE');respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);
