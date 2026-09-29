<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/checkout.php';

$code=preg_replace('/[^a-z0-9_-]/','',(string)($_GET['app']??''));
$allowed=['zdravi','finance','investice','cile','vztahy','rozvoj','firma','podnikani','prace'];
if(!in_array($code,$allowed,true)){http_response_code(404);exit('Aplikace nebyla nalezena.');}
$user=current_session($pdo);
if(!$user){header('Location: /prihlaseni.html?next='.rawurlencode('/api/apps/open.php?app='.$code),true,302);exit;}
ensure_checkout_schema($pdo);
if(!has_active_product($pdo,(int)$user['id'],$code)){header('Location: /moje-aplikace.html?access=denied&app='.rawurlencode($code),true,302);exit;}

/*
 * This endpoint is the mandatory server-side launch gate. Public product/SEO
 * pages remain public. Real private application targets can be configured
 * with APP_URL_<CODE> environment variables when each application runtime is
 * connected. Until then we return to the licensed-app dashboard rather than
 * exposing a public sales page as if it were the private application.
 */
$envKey='APP_URL_'.strtoupper($code);
$target=trim((string)(getenv($envKey)?:''));
if($target!=='' && filter_var($target,FILTER_VALIDATE_URL)){
    $parts=parse_url($target);$scheme=strtolower((string)($parts['scheme']??''));
    if($scheme==='https'){header('Cache-Control: no-store');header('Location: '.$target,true,302);exit;}
}
header('Location: /moje-aplikace.html?access=granted&app='.rawurlencode($code),true,302);exit;
