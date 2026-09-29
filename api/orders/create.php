<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/security.php';
require_once dirname(__DIR__).'/checkout.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);}
require_same_origin();$pdo=db(true);$user=require_csrf($pdo);$d=json_input();
if(($d['currency']??'CZK')!=='CZK')respond(['ok'=>false,'error'=>'CURRENCY_UNAVAILABLE','message'=>'Objednávku nyní připravte v českých korunách.'],422);
foreach(['recurring','terms','privacy','digital'] as $consent)if(!in_array($d[$consent]??null,['on',true,1],true))respond(['ok'=>false,'error'=>'CONSENT_REQUIRED','message'=>'Potvrďte prosím podmínky objednávky.'],422);
$street=trim((string)($d['street']??''));$city=trim((string)($d['city']??''));$zip=trim((string)($d['zip']??''));$country=trim((string)($d['country']??''));
if($street===''||$city===''||$zip===''||$country==='')respond(['ok'=>false,'error'=>'VALIDATION_ERROR','message'=>'Doplňte fakturační adresu.'],422);
ensure_checkout_schema($pdo);
$raw=$d['apps']??[];if(!is_array($raw))respond(['ok'=>false,'error'=>'VALIDATION_ERROR','message'=>'Neplatný obsah košíku.'],422);
$map=['portfolio'=>'investice','investice'=>'investice','zdravi'=>'zdravi','finance'=>'finance','cile'=>'cile','vztahy'=>'vztahy','rozvoj'=>'rozvoj','firma'=>'firma','podnikani'=>'podnikani','prace'=>'prace'];
$codes=[];foreach($raw as $v){$v=strtolower(trim((string)$v));if(isset($map[$v]))$codes[]=$map[$v];}$codes=array_values(array_unique($codes));if(!$codes)respond(['ok'=>false,'error'=>'EMPTY_CART','message'=>'Nejdřív vyberte alespoň jednu aplikaci.'],422);
$placeholders=implode(',',array_fill(0,count($codes),'?'));$q=$pdo->prepare("SELECT id,code,name,price_minor,currency FROM ksa_products WHERE code IN ($placeholders) AND is_active=1");$q->execute($codes);$products=$q->fetchAll(PDO::FETCH_ASSOC);if(count($products)!==count($codes))respond(['ok'=>false,'error'=>'PRODUCT_UNAVAILABLE','message'=>'Některá aplikace už není dostupná. Obnovte stránku.'],409);
$subtotal=0;foreach($products as $p)$subtotal+=(int)$p['price_minor'];$count=count($products);$total=$count>=8?299000:($count===3?129000:$subtotal);$discount=max(0,$subtotal-$total);$number='KSA-'.gmdate('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
$first=trim((string)($d['firstName']??$user['first_name']??''));$last=trim((string)($d['lastName']??$user['last_name']??''));$email=strtolower(trim((string)($d['email']??$user['email']??'')));$phone=trim((string)($d['phone']??$user['phone']??''));if($first===''||$last===''||!filter_var($email,FILTER_VALIDATE_EMAIL))respond(['ok'=>false,'error'=>'VALIDATION_ERROR','message'=>'Doplňte jméno, příjmení a platný e-mail.'],422);
try{$pdo->beginTransaction();$s=$pdo->prepare('INSERT INTO ksa_orders(order_number,user_id,customer_email,customer_first_name,customer_last_name,customer_phone,billing_street,billing_city,billing_postal_code,billing_country,subtotal_minor,discount_minor,total_minor,currency,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,"CZK","pending",UTC_TIMESTAMP())');$s->execute([$number,(int)$user['id'],$email,$first,$last,$phone!==''?$phone:null,$street,$city,$zip,$country,$subtotal,$discount,$total]);$orderId=(int)$pdo->lastInsertId();$i=$pdo->prepare('INSERT INTO ksa_order_items(order_id,product_code,product_name,quantity,unit_price_minor,total_minor,created_at) VALUES(?,?,?,1,?,?,UTC_TIMESTAMP())');foreach($products as $p)$i->execute([$orderId,$p['code'],$p['name'],(int)$p['price_minor'],(int)$p['price_minor']]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Order create failed: '.$e->getMessage());respond(['ok'=>false,'error'=>'ORDER_CREATE_FAILED','message'=>'Objednávku se nepodařilo uložit.'],500);}
respond(['ok'=>true,'order'=>['id'=>$orderId,'number'=>$number,'status'=>'pending','totalMinor'=>$total,'currency'=>'CZK','itemCount'=>$count]],201);
