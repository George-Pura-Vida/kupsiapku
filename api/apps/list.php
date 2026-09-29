<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/checkout.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate');

function apps_list_request_id(): string {
    try { return bin2hex(random_bytes(6)); }
    catch(Throwable $ignored) { return substr(hash('sha256',uniqid('',true)),0,12); }
}
function apps_list_fail(Throwable $e,string $stage): never {
    $requestId=apps_list_request_id();
    error_log(sprintf('[apps/list][%s][%s] %s: %s in %s:%d',$requestId,$stage,get_class($e),$e->getMessage(),basename($e->getFile()),$e->getLine()));
    respond(['ok'=>false,'error'=>'APPS_LIST_UNAVAILABLE','requestId'=>$requestId],200);
}
function apps_list_driver(PDO $pdo): string { return strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)); }
function apps_list_columns(PDO $pdo,string $driver): array {
    if($driver==='mysql') {
        $rows=$pdo->query('SHOW COLUMNS FROM `ksa_user_products`')->fetchAll(PDO::FETCH_ASSOC);
        return array_values(array_filter(array_map(static fn(array $r): string => (string)($r['Field']??''),$rows)));
    }
    if($driver==='sqlite') {
        $rows=$pdo->query('PRAGMA table_info(ksa_user_products)')->fetchAll(PDO::FETCH_ASSOC);
        return array_values(array_filter(array_map(static fn(array $r): string => (string)($r['name']??''),$rows)));
    }
    throw new RuntimeException('Unsupported database driver');
}

try {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET') respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);
    try { $pdo=db(); } catch(Throwable $e) { apps_list_fail($e,'db'); }
    try { $user=require_user($pdo); } catch(Throwable $e) { apps_list_fail($e,'auth'); }

    try {
        $driver=apps_list_driver($pdo);
        if($driver==='mysql') ensure_checkout_schema($pdo);
        $columns=apps_list_columns($pdo,$driver);
        $required=['user_id','product_code','status','activated_at'];
        $missing=array_values(array_diff($required,$columns));
        if($missing) throw new RuntimeException('ksa_user_products schema mismatch: '.implode(',',$missing));
    } catch(Throwable $e) { apps_list_fail($e,'schema'); }

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
        $q=$pdo->prepare("SELECT product_code, MIN(activated_at) AS activated_at FROM ksa_user_products WHERE user_id=? AND status='active' GROUP BY product_code ORDER BY MIN(activated_at), product_code");
        $q->execute([(int)$user['id']]);
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);
    } catch(Throwable $e) { apps_list_fail($e,'query'); }

    $apps=[];
    foreach($rows as $row){
        $code=(string)$row['product_code'];
        $route=$routes[$code]??['name'=>$code,'icon'=>'📱'];
        $apps[]=['code'=>$code,'name'=>$route['name'],'icon'=>$route['icon'],'url'=>'/api/apps/open.php?app='.rawurlencode($code),'activatedAt'=>$row['activated_at']??null];
    }
    respond(['ok'=>true,'user'=>['id'=>(int)$user['id'],'email'=>$user['email']??'','firstName'=>$user['first_name']??'','lastName'=>$user['last_name']??''],'apps'=>$apps,'count'=>count($apps)]);
} catch(Throwable $e) { apps_list_fail($e,'unexpected'); }
