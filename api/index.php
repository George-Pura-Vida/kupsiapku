<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['ok'=>true,'service'=>'Kup si apku API','version'=>1], JSON_UNESCAPED_UNICODE);
