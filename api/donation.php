<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(['ok'=>false,'error'=>'Použijte POST.'], 405);
$data = json_input();
$amount = (int)($data['amount'] ?? 0);
if ($amount < 50 || $amount > 100000) respond(['ok'=>false,'error'=>'Částka musí být od 50 do 100 000 Kč.'], 422);
$email = strtolower(clean_string($data,'email'));
if ($email !== '' && !filter_var($email,FILTER_VALIDATE_EMAIL)) respond(['ok'=>false,'error'=>'Zadejte platný e-mail.'], 422);
$publicId = strtoupper(bin2hex(random_bytes(5)));
$variable = (string)(date('ymd').random_int(1000,9999));
$stmt = db()->prepare('INSERT INTO donations(public_id,amount_minor,donor_name,donor_email,message,variable_symbol,created_at) VALUES(?,?,?,?,?,?,?)');
$stmt->execute([$publicId,$amount*100,clean_string($data,'name'),$email,clean_string($data,'message',500),$variable,gmdate('c')]);
$account = trim((string)getenv('DONATION_ACCOUNT'));
$message = 'Dar Kup si apku '.$publicId;
$spayd = $account !== '' ? 'SPD*1.0*ACC:'.$account.'*AM:'.number_format($amount,2,'.','').'*CC:CZK*X-VS:'.$variable.'*MSG:'.$message : null;
respond(['ok'=>true,'donation'=>['id'=>$publicId,'amount'=>$amount,'variableSymbol'=>$variable,'message'=>$message,'paymentQrPayload'=>$spayd,'accountConfigured'=>$account!==''] ],201);

