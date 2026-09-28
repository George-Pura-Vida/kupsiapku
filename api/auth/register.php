<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth.php';
require_once dirname(__DIR__).'/security.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);}require_same_origin();$pdo=db(true);ensure_auth_schema($pdo);$d=json_input();
$first=trim((string)($d['firstName']??''));$last=trim((string)($d['lastName']??''));$email=strtolower(trim((string)($d['email']??'')));$phone=trim((string)($d['phone']??''));$password=(string)($d['password']??'');
$ip=client_ip();rate_limit_check($pdo,'register_ip',$ip,10,3600,3600);rate_limit_check($pdo,'register_email',$email,5,3600,3600);
$errors=[];if($first===''||mb_strlen($first)>100)$errors['firstName']='Zadejte jméno.';if($last===''||mb_strlen($last)>100)$errors['lastName']='Zadejte příjmení.';if($email===''||mb_strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL))$errors['email']='Zadejte platný e-mail.';if($phone!==''&&mb_strlen($phone)>40)$errors['phone']='Telefon je příliš dlouhý.';if(strlen($password)<8)$errors['password']='Heslo musí mít alespoň 8 znaků.';if(strlen($password)>4096)$errors['password']='Heslo je příliš dlouhé.';
if($errors){rate_limit_hit($pdo,'register_ip',$ip,3600);respond(['ok'=>false,'error'=>'VALIDATION_ERROR','fields'=>$errors],422);}rate_limit_hit($pdo,'register_ip',$ip,3600);rate_limit_hit($pdo,'register_email',$email,3600);
$hash=password_hash($password,PASSWORD_DEFAULT);if($hash===false)respond(['ok'=>false,'error'=>'REGISTRATION_FAILED'],500);
try{$pdo->beginTransaction();$s=$pdo->prepare('INSERT INTO users(email,password_hash,first_name,last_name,phone,role,status,created_at) VALUES(?,?,?,?,?,"customer","active",UTC_TIMESTAMP())');$s->execute([$email,$hash,$first,$last,$phone!==''?$phone:null]);$id=(int)$pdo->lastInsertId();$csrf=create_auth_session($pdo,$id);$pdo->commit();rate_limit_reset($pdo,'register_email',$email);}catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if((string)$e->getCode()==='23000')respond(['ok'=>false,'error'=>'EMAIL_EXISTS','message'=>'Účet s tímto e-mailem již existuje.'],409);error_log('Registration failed: '.$e->getMessage());respond(['ok'=>false,'error'=>'REGISTRATION_FAILED','message'=>'Registraci se nepodařilo dokončit.'],500);}
respond(['ok'=>true,'user'=>['id'=>$id,'email'=>$email,'firstName'=>$first,'lastName'=>$last],'csrfToken'=>$csrf],201);
