<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/checkout.php';

if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET') respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);
$user=require_user($pdo);

$routes=[
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

try {
    // Keep the licence endpoint consistent with checkout/open.php. On installations
    // deployed before entitlements existed this creates the missing licence tables
    // instead of failing the page with APPS_LIST_FAILED.
    ensure_checkout_schema($pdo);

    $q=$pdo->prepare("SELECT up.product_code, MIN(up.activated_at) AS activated_at, MAX(p.name) AS product_name
        FROM ksa_user_products up
        LEFT JOIN ksa_products p ON p.code=up.product_code
        WHERE up.user_id=? AND up.status='active'
        GROUP BY up.product_code
        ORDER BY MIN(up.activated_at), up.product_code");
    $q->execute([(int)$user['id']]);
    $apps=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
        $code=(string)$row['product_code'];
        $route=$routes[$code]??['name'=>(string)($row['product_name']?:$code),'icon'=>'📱'];
        $apps[]=['code'=>$code,'name'=>$route['name'],'icon'=>$route['icon'],'url'=>'/api/apps/open.php?app='.rawurlencode($code),'activatedAt'=>$row['activated_at']];
    }
    respond(['ok'=>true,'user'=>['id'=>(int)$user['id'],'email'=>$user['email'],'firstName'=>$user['first_name'],'lastName'=>$user['last_name']],'apps'=>$apps,'count'=>count($apps)]);
} catch(Throwable $e) {
    error_log('apps/list failed: '.get_class($e).': '.$e->getMessage());
    respond(['ok'=>false,'error'=>'APPS_LIST_FAILED','message'=>'Seznam aplikací se nyní nepodařilo načíst.'],500);
}
