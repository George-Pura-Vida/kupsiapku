<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/checkout.php';

$code=preg_replace('/[^a-z0-9_-]/','',(string)($_GET['app']??''));
$allowed=['zdravi','finance','investice','cile','vztahy','rozvoj','firma','podnikani','prace'];
if(!in_array($code,$allowed,true)){http_response_code(404);exit('Aplikace nebyla nalezena.');}
try{$pdo=db();}catch(Throwable $e){$requestId=substr(hash('sha256',uniqid('',true)),0,12);error_log('[apps/open]['.$requestId.'] DB init failed: '.$e->getMessage());http_response_code(503);header('Content-Type: text/plain; charset=utf-8');exit('Aplikaci se nyní nepodařilo otevřít. ID: '.$requestId);}
$user=current_session($pdo);
if(!$user){header('Location: /prihlaseni.html?next='.rawurlencode('/api/apps/open.php?app='.$code),true,302);exit;}
ensure_checkout_schema($pdo);
if(!has_active_product($pdo,(int)$user['id'],$code)){header('Location: /moje-aplikace.html?access=denied&app='.rawurlencode($code),true,302);exit;}
header('Cache-Control: private, no-store');
$targets=[
'zdravi'=>'https://energie.jirijanousek.cz/',
'finance'=>'/api/apps/sso.php?action=issue',
'investice'=>'https://portfolio.jirijanousek.cz/',
'cile'=>'/api/apps/cile.php',
];
$target=$targets[$code]??('/api/apps/private.php?app='.rawurlencode($code));
header('Location: '.$target,true,302);exit;
