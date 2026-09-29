<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/checkout.php';

function ensure_sso_schema(PDO $pdo): void {
    $pdo->exec('CREATE TABLE IF NOT EXISTS ksa_app_sso_tokens (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,token_hash CHAR(64) NOT NULL UNIQUE,user_id BIGINT UNSIGNED NOT NULL,app_code VARCHAR(50) NOT NULL,expires_at DATETIME NOT NULL,used_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_sso_expiry(expires_at),KEY idx_sso_user_app(user_id,app_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}
function sso_request_id(): string { return substr(bin2hex(random_bytes(8)),0,12); }

$pdo=db();
ensure_auth_schema($pdo);
ensure_checkout_schema($pdo);
ensure_sso_schema($pdo);
$action=(string)($_GET['action']??'');

if($action==='issue'){
    $user=current_session($pdo);
    if(!$user){http_response_code(401);exit('AUTH_REQUIRED');}
    if(!has_active_product($pdo,(int)$user['id'],'finance')){http_response_code(403);exit('LICENSE_REQUIRED');}
    $token=bin2hex(random_bytes(32));
    $hash=hash('sha256',$token);
    $expires=gmdate('Y-m-d H:i:s',time()+90);
    $pdo->prepare('INSERT INTO ksa_app_sso_tokens(token_hash,user_id,app_code,expires_at) VALUES(?,?,?,?)')->execute([$hash,(int)$user['id'],'finance',$expires]);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('Referrer-Policy: no-referrer');
    echo '<!doctype html><meta charset="utf-8"><title>Otevírám Moje finance</title><form id="s" method="post" action="https://budget.jirijanousek.cz/sso.php"><input type="hidden" name="token" value="'.htmlspecialchars($token,ENT_QUOTES,'UTF-8').'"></form><script>document.getElementById("s").submit()</script><noscript><button form="s">Pokračovat</button></noscript>';
    exit;
}

if($action==='redeem'){
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $requestId=sso_request_id();
    $secret=(string)getenv('FINANCE_SSO_SECRET');
    $provided=(string)($_SERVER['HTTP_X_FINANCE_SSO_SECRET']??'');
    if($secret===''||$provided===''||!hash_equals($secret,$provided)){
        error_log('[finance-sso]['.$requestId.'] redeem denied: invalid service secret');
        respond(['ok'=>false,'requestId'=>$requestId],403);
    }
    $in=json_input();
    $token=(string)($in['token']??'');
    if(!preg_match('/^[a-f0-9]{64}$/',$token)){
        error_log('[finance-sso]['.$requestId.'] redeem denied: malformed token');
        respond(['ok'=>false,'requestId'=>$requestId],400);
    }
    try{
        $pdo->beginTransaction();
        $s=$pdo->prepare('SELECT t.id,t.user_id,t.app_code,u.email,u.first_name,u.last_name FROM ksa_app_sso_tokens t JOIN users u ON u.id=t.user_id WHERE t.token_hash=? AND t.used_at IS NULL AND t.expires_at>UTC_TIMESTAMP() LIMIT 1 FOR UPDATE');
        $s->execute([hash('sha256',$token)]);
        $row=$s->fetch();
        if(!$row||$row['app_code']!=='finance'||!has_active_product($pdo,(int)$row['user_id'],'finance')){
            $pdo->rollBack();
            error_log('[finance-sso]['.$requestId.'] redeem denied: expired/used/unlicensed');
            respond(['ok'=>false,'requestId'=>$requestId],403);
        }
        $pdo->prepare('UPDATE ksa_app_sso_tokens SET used_at=UTC_TIMESTAMP() WHERE id=?')->execute([(int)$row['id']]);
        $pdo->commit();
        respond(['ok'=>true,'user'=>['id'=>(int)$row['user_id'],'email'=>$row['email'],'firstName'=>$row['first_name'],'lastName'=>$row['last_name']]]);
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        error_log('[finance-sso]['.$requestId.'] redeem failed: '.$e->getMessage());
        respond(['ok'=>false,'requestId'=>$requestId],500);
    }
}

http_response_code(404);exit;
