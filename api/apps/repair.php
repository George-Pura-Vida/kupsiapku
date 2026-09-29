<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/checkout.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, no-cache, must-revalidate');

function repair_request_id(): string {
    try { return bin2hex(random_bytes(6)); }
    catch(Throwable $e) { return substr(hash('sha256',uniqid('',true)),0,12); }
}

try {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST') respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);
    $pdo=db();
    $user=require_user($pdo);
    ensure_checkout_schema($pdo);
    $userId=(int)$user['id'];

    $q=$pdo->prepare('SELECT id,order_number,status,created_at FROM ksa_orders WHERE user_id=? ORDER BY id DESC');
    $q->execute([$userId]);
    $orders=$q->fetchAll(PDO::FETCH_ASSOC);

    $summary=[];$repaired=0;
    foreach($orders as $order){
        $orderId=(int)$order['id'];
        $itemsQ=$pdo->prepare('SELECT product_code,product_name,quantity FROM ksa_order_items WHERE order_id=? ORDER BY id');
        $itemsQ->execute([$orderId]);
        $items=$itemsQ->fetchAll(PDO::FETCH_ASSOC);

        $licQ=$pdo->prepare('SELECT product_code,status FROM ksa_user_products WHERE user_id=? AND order_id=? ORDER BY id');
        $licQ->execute([$userId,$orderId]);
        $before=$licQ->fetchAll(PDO::FETCH_ASSOC);

        $fixed=0;
        if((string)$order['status']==='paid' && $items){
            $fixed=grant_order_products($pdo,$orderId,$userId);
            $repaired+=$fixed;
        }

        $licQ->execute([$userId,$orderId]);
        $after=$licQ->fetchAll(PDO::FETCH_ASSOC);
        $summary[]=[
            'orderNumber'=>(string)$order['order_number'],
            'status'=>(string)$order['status'],
            'itemCount'=>count($items),
            'items'=>array_map(static fn(array $i): array => ['code'=>(string)$i['product_code'],'name'=>(string)$i['product_name'],'quantity'=>(int)$i['quantity']],$items),
            'licencesBefore'=>array_map(static fn(array $l): array => ['code'=>(string)$l['product_code'],'status'=>(string)$l['status']],$before),
            'licencesAfter'=>array_map(static fn(array $l): array => ['code'=>(string)$l['product_code'],'status'=>(string)$l['status']],$after),
            'repairAttempted'=>((string)$order['status']==='paid' && count($items)>0),
            'repairedCount'=>$fixed
        ];
    }

    $activeQ=$pdo->prepare('SELECT product_code FROM ksa_user_products WHERE user_id=? AND status="active" ORDER BY product_code');
    $activeQ->execute([$userId]);
    $active=array_values(array_map('strval',$activeQ->fetchAll(PDO::FETCH_COLUMN)));

    respond([
        'ok'=>true,
        'userId'=>$userId,
        'orders'=>$summary,
        'activeProducts'=>$active,
        'activeCount'=>count($active),
        'repairOperations'=>$repaired
    ]);
} catch(Throwable $e) {
    $requestId=repair_request_id();
    error_log('[apps/repair]['.$requestId.'] '.get_class($e).': '.$e->getMessage());
    respond(['ok'=>false,'error'=>'REPAIR_UNAVAILABLE','requestId'=>$requestId],200);
}
