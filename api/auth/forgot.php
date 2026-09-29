<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/security.php';
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { header('Allow: POST'); respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405); }
require_same_origin();
$pdo=db(true); ensure_auth_schema($pdo);
$data=json_input(); $email=strtolower(trim((string)($data['email']??''))); $ip=client_ip();
rate_limit_check($pdo,'forgot_ip',$ip,10,3600,3600);
rate_limit_check($pdo,'forgot_email',$email,3,3600,3600);
rate_limit_hit($pdo,'forgot_ip',$ip,3600);
rate_limit_hit($pdo,'forgot_email',$email,3600);
if (strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) respond(['ok'=>false,'error'=>'INVALID_EMAIL','message'=>'Zadejte platný e-mail.'],422);
$q=$pdo->prepare('SELECT id FROM users WHERE email=? AND status="active" LIMIT 1'); $q->execute([$email]); $userId=$q->fetchColumn();
if ($userId) {
    $token=bin2hex(random_bytes(32)); $digest=hash('sha256',$token);
    $pdo->prepare('DELETE FROM auth_password_resets WHERE user_id=? OR expires_at<UTC_TIMESTAMP()')->execute([$userId]);
    $pdo->prepare('INSERT INTO auth_password_resets(token_hash,user_id,expires_at) VALUES(?,?,?)')->execute([$digest,$userId,gmdate('Y-m-d H:i:s',time()+1800)]);
    $link='https://kupsiapku.cz/nove-heslo.html#reset='.$token;
    $body="Obnova hesla KupSiApku.cz\n\nOtevřete tento jednorázový odkaz do 30 minut:\n".$link."\n\nPokud jste obnovu nežádali, zprávu ignorujte.\n";
    $sent=@mail($email,'=?UTF-8?B?'.base64_encode('Obnova hesla KupSiApku.cz').'?=',$body,"MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: Kup si apku <info@jirijanousek.cz>");
    if (!$sent) { $pdo->prepare('DELETE FROM auth_password_resets WHERE token_hash=?')->execute([$digest]); error_log('Customer reset mail transport failed.'); }
}
respond(['ok'=>true,'message'=>'Pokud je e-mail přiřazen k účtu, přijde na něj odkaz pro obnovu hesla. Zkontrolujte i spam.']);
