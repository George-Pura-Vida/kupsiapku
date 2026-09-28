<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth.php';require_once dirname(__DIR__).'/security.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);}require_same_origin();$pdo=db(true);ensure_auth_schema($pdo);$d=json_input();$email=strtolower(trim((string)($d['email']??'')));$password=(string)($d['password']??'');$ip=client_ip();
rate_limit_check($pdo,'login_ip',$ip,20,900,900);rate_limit_check($pdo,'login_email',$email,5,900,900);
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$password===''){rate_limit_hit($pdo,'login_ip',$ip,900);rate_limit_hit($pdo,'login_email',$email,900);respond(['ok'=>false,'error'=>'INVALID_CREDENTIALS','message'=>'Nesprávný e-mail nebo heslo.'],401);}
$s=$pdo->prepare('SELECT id,email,password_hash,first_name,last_name,role,status FROM users WHERE email=? LIMIT 1');$s->execute([$email]);$u=$s->fetch();
if(!$u||empty($u['password_hash'])||!password_verify($password,(string)$u['password_hash'])){rate_limit_hit($pdo,'login_ip',$ip,900);rate_limit_hit($pdo,'login_email',$email,900);respond(['ok'=>false,'error'=>'INVALID_CREDENTIALS','message'=>'Nesprávný e-mail nebo heslo.'],401);}if($u['status']!=='active')respond(['ok'=>false,'error'=>'ACCOUNT_UNAVAILABLE','message'=>'Účet momentálně není dostupný.'],403);
if(password_needs_rehash((string)$u['password_hash'],PASSWORD_DEFAULT)){if(($h=password_hash($password,PASSWORD_DEFAULT))!==false)$pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$h,$u['id']]);}
rate_limit_reset($pdo,'login_email',$email);$csrf=create_auth_session($pdo,(int)$u['id']);respond(['ok'=>true,'user'=>['id'=>(int)$u['id'],'email'=>$u['email'],'firstName'=>$u['first_name'],'lastName'=>$u['last_name'],'role'=>$u['role']],'csrfToken'=>$csrf]);
