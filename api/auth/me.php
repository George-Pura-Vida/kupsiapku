<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth.php';
if($_SERVER['REQUEST_METHOD']!=='GET'){header('Allow: GET');respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);}$pdo=db(true);$s=current_session($pdo);if(!$s)respond(['ok'=>true,'authenticated'=>false]);$csrf=rotate_csrf($pdo,(int)$s['session_id']);respond(['ok'=>true,'authenticated'=>true,'csrfToken'=>$csrf,'user'=>['id'=>(int)$s['id'],'email'=>$s['email'],'firstName'=>$s['first_name'],'lastName'=>$s['last_name'],'phone'=>$s['phone'],'role'=>$s['role']]]);
