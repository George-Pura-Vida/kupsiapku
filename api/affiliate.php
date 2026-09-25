<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'POST' && $action === 'register') {
    $data = json_input();
    foreach (['firstName','lastName','email','phone','street','zip','city','country'] as $key) {
        if (clean_string($data, $key) === '') respond(['ok'=>false,'error'=>'Vyplňte všechna povinná pole.'], 422);
    }
    $email = strtolower(clean_string($data, 'email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(['ok'=>false,'error'=>'Zadejte platný e-mail.'], 422);
    if (empty($data['privacy'])) respond(['ok'=>false,'error'=>'Je nutné potvrdit zpracování osobních údajů.'], 422);
    $existing = $pdo->prepare('SELECT * FROM affiliates WHERE email = ?');
    $existing->execute([$email]);
    if ($existing->fetch()) respond(['ok'=>false,'error'=>'Pro tento e-mail už profil existuje. Použijte zařízení, na kterém byl vytvořen.'], 409);
    $token = bin2hex(random_bytes(32));
    $code = unique_code($pdo, clean_string($data,'firstName'), clean_string($data,'lastName'));
    $stmt = $pdo->prepare('INSERT INTO affiliates(code,access_token_hash,first_name,last_name,email,phone,street,zip,city,country,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$code,hash('sha256',$token),clean_string($data,'firstName'),clean_string($data,'lastName'),$email,clean_string($data,'phone'),clean_string($data,'street'),clean_string($data,'zip'),clean_string($data,'city'),clean_string($data,'country'),gmdate('c')]);
    $shareUrl = 'https://kupsiapku.cz/?ref='.rawurlencode($code);
    $profileUrl = 'https://kupsiapku.cz/doporucit.html?code='.rawurlencode($code);
    $subject = '=?UTF-8?B?'.base64_encode('Tvůj doporučitelský kód | Kup si apku').'?=';
    $body = '<!doctype html><html lang="cs"><body style="font-family:Arial,sans-serif;color:#102a56;line-height:1.6"><h1>Tvůj doporučitelský kód je připravený</h1><p>Ahoj '.htmlspecialchars(clean_string($data,'firstName'),ENT_QUOTES,'UTF-8').',</p><p>osobní kód: <strong>'.htmlspecialchars($code,ENT_QUOTES,'UTF-8').'</strong></p><p><a href="'.htmlspecialchars($profileUrl,ENT_QUOTES,'UTF-8').'" style="display:inline-block;padding:12px 18px;background:#ef2d63;color:#fff;text-decoration:none;border-radius:10px">Otevřít affiliate stránku</a></p><p>QR kód a statistiky najdeš na stránce programu v zařízení, kde ses registroval. Veřejný QR náhled: <a href="'.htmlspecialchars($profileUrl,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($profileUrl,ENT_QUOTES,'UTF-8').'</a></p><p>Kup si apku</p></body></html>';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Kup si apku <info@jirijanousek.cz>',
        'Reply-To: info@jirijanousek.cz',
    ];
    $emailSent = @mail($email, $subject, $body, implode("\r\n", $headers));
    respond(['ok'=>true,'token'=>$token,'emailSent'=>$emailSent,'profile'=>['code'=>$code,'firstName'=>clean_string($data,'firstName'),'shareUrl'=>$shareUrl]] ,201);
}


if ($method === 'POST' && $action === 'resend') {
    $affiliate = affiliate_from_token($pdo, (string)($_SERVER['HTTP_X_AFFILIATE_TOKEN'] ?? ''));
    if (!$affiliate) respond(['ok'=>false,'error'=>'Přihlášení partnera není platné.'], 401);
    $shareUrl = 'https://kupsiapku.cz/?ref='.rawurlencode($affiliate['code']);
    $profileUrl = 'https://kupsiapku.cz/doporucit.html?code='.rawurlencode($affiliate['code']);
    $subject = '=?UTF-8?B?'.base64_encode('Tvůj doporučitelský kód | Kup si apku').'?=';
    $body = '<!doctype html><html lang="cs"><body style="font-family:Arial,sans-serif;color:#102a56;line-height:1.6"><h1>Tvůj doporučitelský kód je připravený</h1><p>Ahoj '.htmlspecialchars($affiliate['first_name'],ENT_QUOTES,'UTF-8').',</p><p>Osobní kód: <strong>'.htmlspecialchars($affiliate['code'],ENT_QUOTES,'UTF-8').'</strong></p><p><a href="'.htmlspecialchars($profileUrl,ENT_QUOTES,'UTF-8').'" style="display:inline-block;padding:12px 18px;background:#ef2d63;color:#fff;text-decoration:none;border-radius:10px">Otevřít affiliate stránku</a></p><p>QR náhled: <a href="'.htmlspecialchars($profileUrl,ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($profileUrl,ENT_QUOTES,'UTF-8').'</a></p><p>Kup si apku</p></body></html>';
    $headers = ['MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8','From: Kup si apku <info@jirijanousek.cz>','Reply-To: info@jirijanousek.cz'];
    $sent = @mail($affiliate['email'], $subject, $body, implode("\r\n", $headers));
    respond(['ok'=>$sent,'emailSent'=>$sent,'message'=>$sent?'E-mail byl znovu odeslán.':'E-mail se nepodařilo odeslat.'], $sent?200:503);
}

if ($method === 'GET' && $action === 'profile') {
    $affiliate = affiliate_from_token($pdo, (string)($_SERVER['HTTP_X_AFFILIATE_TOKEN'] ?? ''));
    if (!$affiliate) respond(['ok'=>false,'error'=>'Přihlášení partnera není platné.'], 401);
    $clicks = $pdo->prepare('SELECT COUNT(*) FROM referral_clicks WHERE affiliate_id = ?');
    $clicks->execute([$affiliate['id']]);
    $stats = $pdo->prepare('SELECT COUNT(*) registrations, SUM(CASE WHEN status="paid" THEN 1 ELSE 0 END) purchases, COALESCE(SUM(CASE WHEN status="paid" THEN commission_minor ELSE 0 END),0) commission FROM referrals WHERE affiliate_id = ?');
    $stats->execute([$affiliate['id']]);
    $row = $stats->fetch();
    respond(['ok'=>true,'profile'=>['code'=>$affiliate['code'],'firstName'=>$affiliate['first_name'],'shareUrl'=>'https://kupsiapku.cz/?ref='.rawurlencode($affiliate['code'])],'stats'=>['clicks'=>(int)$clicks->fetchColumn(),'registrations'=>(int)$row['registrations'],'purchases'=>(int)$row['purchases'],'commissionMinor'=>(int)$row['commission']]]);
}

if ($method === 'POST' && $action === 'click') {
    $data = json_input();
    $code = strtoupper(clean_string($data,'code',50));
    $stmt = $pdo->prepare('SELECT id FROM affiliates WHERE code = ?');
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();
    if (!$id) respond(['ok'=>false,'error'=>'Neplatný doporučitelský kód.'], 404);
    $fingerprint = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '').'|'.($_SERVER['HTTP_USER_AGENT'] ?? '').'|'.gmdate('Y-m-d'));
    $insert = $pdo->prepare('INSERT OR IGNORE INTO referral_clicks(affiliate_id,visitor_hash,landing_page,created_at) VALUES(?,?,?,?)');
    $insert->execute([$id,$fingerprint,clean_string($data,'landingPage',255),gmdate('c')]);
    respond(['ok'=>true]);
}

respond(['ok'=>false,'error'=>'Neznámá operace.'], 404);

