<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate');

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
    // Runtime endpoint is intentionally read-only. Database migrations/schema
    // creation must never run while a customer is opening "Moje aplikace".
    $q=$pdo->prepare("SELECT up.product_code, MIN(up.activated_at) AS activated_at
        FROM ksa_user_products up
        WHERE up.user_id=? AND up.status='active'
        GROUP BY up.product_code
        ORDER BY MIN(up.activated_at), up.product_code");
    $q->execute([(int)$user['id']]);

    $apps=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
        $code=(string)$row['product_code'];
        $route=$routes[$code]??['name'=>$code,'icon'=>'📱'];
        $apps[]=[
            'code'=>$code,
            'name'=>$route['name'],
            'icon'=>$route['icon'],
            'url'=>'/api/apps/open.php?app='.rawurlencode($code),
            'activatedAt'=>$row['activated_at']
        ];
    }

    respond([
        'ok'=>true,
        'user'=>[
            'id'=>(int)$user['id'],
            'email'=>$user['email'],
            'firstName'=>$user['first_name'],
            'lastName'=>$user['last_name']
        ],
        'apps'=>$apps,
        'count'=>count($apps)
    ]);
} catch(Throwable $e) {
    // Keep sensitive SQL/DB details in the server log only. The request id lets
    // an administrator correlate the public error with the matching log entry.
    try {
        $requestId=bin2hex(random_bytes(6));
    } catch(Throwable $ignored) {
        $requestId=substr(hash('sha256',uniqid('',true)),0,12);
    }
    error_log(sprintf(
        '[apps/list][%s] %s: %s in %s:%d',
        $requestId,
        get_class($e),
        $e->getMessage(),
        basename($e->getFile()),
        $e->getLine()
    ));

    // Deliberately return a controlled JSON response instead of an HTTP 500 so
    // the frontend can render a useful state while the server log keeps details.
    respond([
        'ok'=>false,
        'error'=>'APPS_LIST_UNAVAILABLE',
        'message'=>'Seznam aplikací se nyní nepodařilo načíst.',
        'requestId'=>$requestId
    ],200);
}
