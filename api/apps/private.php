<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/checkout.php';

$apps=[
 'zdravi'=>['name'=>'Moje zdraví','icon'=>'❤️'],
 'finance'=>['name'=>'Moje finance','icon'=>'💼'],
 'investice'=>['name'=>'Moje portfolio','icon'=>'📈'],
 'cile'=>['name'=>'Moje cíle a góly','icon'=>'🎯'],
 'vztahy'=>['name'=>'Moje vztahy','icon'=>'🤝'],
 'rozvoj'=>['name'=>'Můj rozvoj','icon'=>'🌱'],
 'firma'=>['name'=>'Moje firma','icon'=>'🏢'],
 'podnikani'=>['name'=>'Moje podnikání','icon'=>'🚀'],
 'prace'=>['name'=>'Nová práce','icon'=>'💼']
];
$code=preg_replace('/[^a-z0-9_-]/','',(string)($_GET['app']??''));
if(!isset($apps[$code])){http_response_code(404);exit('Aplikace nebyla nalezena.');}
$user=current_session($pdo);
if(!$user){$next='/api/apps/private.php?app='.$code;header('Location: /prihlaseni.html?next='.rawurlencode($next),true,302);exit;}
ensure_checkout_schema($pdo);
if(!has_active_product($pdo,(int)$user['id'],$code)){header('Location: /moje-aplikace.html?access=denied&app='.rawurlencode($code),true,302);exit;}
header('Cache-Control: private, no-store, no-cache, must-revalidate');header('Pragma: no-cache');header('X-Robots-Tag: noindex, nofollow',true);header('X-Frame-Options: DENY');header('X-Content-Type-Options: nosniff');
$app=$apps[$code];$name=htmlspecialchars($app['name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$icon=htmlspecialchars($app['icon'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$first=htmlspecialchars((string)($user['first_name']??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?><!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= $name ?> | Soukromá aplikace</title><link rel="stylesheet" href="/style.css?v=20260929-private"></head><body><main class="accountMain"><section class="accountShell"><div class="eyebrow">Soukromá aplikace</div><h1><?= $icon ?> <?= $name ?></h1><p><?= $first!==''?'Ahoj '.$first.'. ':'' ?>Přístup byl ověřen podle tvého přihlášení a aktivní licence.</p><div class="accountPanel"><div class="accountRow"><b>🔐 Přístup aktivní</b><span>Tento vstup není veřejná prodejní stránka. Obsah a data aplikace budou obsluhovány pouze v kontextu přihlášeného uživatele.</span></div></div><div class="accountActions"><a class="btn" href="/moje-aplikace.html">← Moje aplikace</a><a class="btn alt" href="/ucet.html">Můj účet</a></div></section></main></body></html>