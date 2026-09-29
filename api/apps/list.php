<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/checkout.php';

if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET') respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);
$user=require_user($pdo);
ensure_checkout_schema($pdo);

$routes=[
 'zdravi'=>['name'=>'Moje zdraví','icon'=>'❤️','url'=>'/aplikace-zdravi.html'],
 'finance'=>['name'=>'Moje finance','icon'=>'💼','url'=>'/aplikace-finance.html'],
 'investice'=>['name'=>'Moje portfolio','icon'=>'📈','url'=>'/aplikace-investice.html'],
 'cile'=>['name'=>'Moje cíle a góly','icon'=>'🎯','url'=>'/aplikace-cile.html'],
 'vztahy'=>['name'=>'Moje vztahy','icon'=>'🤝','url'=>'/aplikace-vztahy.html'],
 'rozvoj'=>['name'=>'Můj rozvoj','icon'=>'🌱','url'=>'/aplikace-rozvoj.html'],
 'firma'=>['name'=>'Moje firma','icon'=>'🏢','url'=>'/aplikace-firma.html'],
 'podnikani'=>['name'=>'Moje podnikání','icon'=>'🚀','url'=>'/aplikace-podnikani.html'],
 'prace'=>['name'=>'Nová práce','icon'=>'💼','url'=>'/aplikace-prace.html']
];
$q=$pdo->prepare('SELECT up.product_code,MIN(up.activated_at) activated_at,p.name FROM ksa_user_products up LEFT JOIN ksa_products p ON p.code=up.product_code WHERE up.user_id=? AND up.status="active" GROUP BY up.product_code,p.name ORDER BY MIN(up.activated_at),up.product_code');
$q->execute([(int)$user['id']]);$apps=[];
foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$code=(string)$row['product_code'];$route=$routes[$code]??['name'=>(string)($row['name']?:$code),'icon'=>'📱','url'=>null];$apps[]=['code'=>$code,'name'=>$route['name'],'icon'=>$route['icon'],'url'=>$route['url'],'activatedAt'=>$row['activated_at']];}
respond(['ok'=>true,'user'=>['id'=>(int)$user['id'],'email'=>$user['email'],'firstName'=>$user['first_name'],'lastName'=>$user['last_name']],'apps'=>$apps,'count'=>count($apps)]);
