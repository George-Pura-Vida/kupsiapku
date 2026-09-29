<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate');

function apps_list_request_id(): string {
    try {
        return bin2hex(random_bytes(6));
    } catch(Throwable $ignored) {
        return substr(hash('sha256',uniqid('',true)),0,12);
    }
}

function apps_list_failure(Throwable $e, array $context=[]): never {
    $requestId=apps_list_request_id();
    $safeContext=[];
    foreach($context as $key=>$value){
        if(is_scalar($value)||$value===null){
            $safeContext[(string)$key]=$value;
        }
    }
    error_log(sprintf(
        '[apps/list][%s] %s: %s in %s:%d context=%s',
        $requestId,
        get_class($e),
        $e->getMessage(),
        basename($e->getFile()),
        $e->getLine(),
        json_encode($safeContext,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
    ));
    respond([
        'ok'=>false,
        'error'=>'APPS_LIST_UNAVAILABLE',
        'requestId'=>$requestId
    ],200);
}

function verify_user_products_schema(PDO $pdo): array {
    $required=[
        'user_id'=>null,
        'product_code'=>null,
        'status'=>null,
        'activated_at'=>null
    ];

    // SHOW COLUMNS is read-only and exposes no schema details to the client.
    // Any concrete DB error is caught by the outer handler and kept server-side.
    $stmt=$pdo->query('SHOW COLUMNS FROM `ksa_user_products`');
    $columns=$stmt->fetchAll(PDO::FETCH_ASSOC);
    if(!$columns){
        throw new RuntimeException('ksa_user_products exists but returned no columns');
    }

    foreach($columns as $column){
        $field=(string)($column['Field']??'');
        if(array_key_exists($field,$required)){
            $required[$field]=(string)($column['Type']??'unknown');
        }
    }

    $missing=[];
    foreach($required as $field=>$type){
        if($type===null)$missing[]=$field;
    }
    if($missing){
        throw new RuntimeException('ksa_user_products missing required columns: '.implode(',',$missing));
    }

    // Return only non-sensitive diagnostic facts for the server log.
    return [
        'table'=>'ksa_user_products',
        'column_count'=>count($columns),
        'required_columns_ok'=>true
    ];
}

try {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET') {
        respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);
    }

    $pdo=db();
    $user=require_user($pdo);

    $schemaContext=verify_user_products_schema($pdo);

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
    apps_list_failure($e,isset($schemaContext)?$schemaContext:['stage'=>'schema_check']);
}
