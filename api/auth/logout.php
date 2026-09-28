<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/auth.php';require_once dirname(__DIR__).'/security.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');respond(['ok'=>false,'error'=>'METHOD_NOT_ALLOWED'],405);}require_same_origin();$pdo=db(true);require_csrf($pdo);revoke_current_session($pdo);respond(['ok'=>true]);
