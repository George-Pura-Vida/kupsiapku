<?php
declare(strict_types=1);

// This module is included by admin.php; it is not a separate public API.
if (!function_exists('respond')) { http_response_code(404); exit; }
header('Referrer-Policy: no-referrer');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('ksa_admin');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
session_start();
$pdo = db(true); // After the first verified login/reset, the database is pinned.
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string)($_GET['action'] ?? 'session');
$mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
$suffix = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
$pdo->exec('CREATE TABLE IF NOT EXISTS admin_auth_state (admin_id BIGINT PRIMARY KEY,password_hash VARCHAR(255) NULL,version CHAR(64) NOT NULL)' . $suffix);
$pdo->exec('CREATE TABLE IF NOT EXISTS admin_reset_tokens (token_hash CHAR(64) PRIMARY KEY,admin_id BIGINT NOT NULL,version CHAR(64) NOT NULL,expires_at BIGINT NOT NULL)' . $suffix);
$pdo->exec('CREATE TABLE IF NOT EXISTS admin_rate_limits (bucket CHAR(64) PRIMARY KEY,hits INTEGER NOT NULL,expires_at BIGINT NOT NULL)' . $suffix);

function admin_user(string $username): ?array {
    global $pdo;
    $q = $pdo->prepare('SELECT * FROM admin_users WHERE username=?');
    $q->execute([$username]);
    $user = $q->fetch();
    if (!$user) return null;
    $prefix = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
    $pdo->prepare($prefix.' INTO admin_auth_state(admin_id,password_hash,version) VALUES(?,NULL,?)')->execute([$user['id'],bin2hex(random_bytes(32))]);
    $q = $pdo->prepare('SELECT password_hash AS modern_hash,version FROM admin_auth_state WHERE admin_id=?');
    $q->execute([$user['id']]);
    return array_merge($user, $q->fetch());
}

function admin_verify(string $password, array $user): bool {
    if (strlen($password) > 1024) return false;
    if ($user['modern_hash'] !== null) return password_verify($password, $user['modern_hash']);
    // Read-only compatibility with the existing salted PBKDF2 records.
    if (!preg_match('/^[a-f0-9]{32}$/i', $user['password_salt'])) return false;
    return hash_equals($user['password_hash'], hash_pbkdf2('sha256',$password,hex2bin($user['password_salt']),120000,64,false));
}

function admin_password_hash(string $password): string {
    // Argon2id avoids bcrypt's 72-byte truncation when supported by the host.
    return defined('PASSWORD_ARGON2ID')
        ? password_hash($password, PASSWORD_ARGON2ID, ['memory_cost'=>19456,'time_cost'=>2,'threads'=>1])
        : password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
}

function admin_new_password(array $data): string {
    $password = (string)($data['newPassword'] ?? '');
    if (mb_strlen($password) < 12 || strlen($password) > 72 || str_contains($password,"\0")) respond(['ok'=>false,'error'=>'Heslo musí mít alespoň 12 znaků a nejvýše 72 bajtů a nesmí obsahovat nulový znak.'],422);
    if ($password !== (string)($data['confirmPassword'] ?? '')) respond(['ok'=>false,'error'=>'Nová hesla se neshodují.'],422);
    return $password;
}

function admin_limit(string $scope, string $subject, int $limit, int $seconds): void {
    global $pdo;
    $now = time();
    $expiry = (intdiv($now,$seconds)+1)*$seconds;
    $key = hash('sha256',$scope.'|'.$subject.'|'.$expiry);
    $pdo->prepare('DELETE FROM admin_rate_limits WHERE expires_at < ?')->execute([$now]);
    $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
        ? 'INSERT INTO admin_rate_limits(bucket,hits,expires_at) VALUES(?,1,?) ON DUPLICATE KEY UPDATE hits=hits+1'
        : 'INSERT INTO admin_rate_limits(bucket,hits,expires_at) VALUES(?,1,?) ON CONFLICT(bucket) DO UPDATE SET hits=hits+1';
    $pdo->prepare($sql)->execute([$key,$expiry]);
    $q=$pdo->prepare('SELECT hits FROM admin_rate_limits WHERE bucket=?'); $q->execute([$key]);
    if ((int)$q->fetchColumn()>$limit) {
        header('Retry-After: '.($expiry-$now));
        respond(['ok'=>false,'error'=>'Příliš mnoho pokusů. Zkuste to později.'],429);
    }
}

function admin_clear_session(): void {
    $_SESSION=[];
    setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    session_destroy();
}

function admin_pin_database(): void {
    global $pdo;
    $driver=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $identity=$driver==='mysql' ? hash('sha256',trim((string)getenv('DB_HOST')).'|'.(getenv('DB_PORT')?:3306).'|'.trim((string)getenv('DB_NAME'))) : 'local';
    $dir=__DIR__.'/data';
    if (!is_dir($dir)) mkdir($dir,0770,true);
    $handle=fopen($dir.'/admin-database.json','c+');
    if (!$handle || !flock($handle,LOCK_EX)) respond(['ok'=>false,'error'=>'Nelze zabezpečit úložiště administrace.'],503);
    $existing=stream_get_contents($handle);
    $pin=['driver'=>$driver,'identity'=>$identity];
    if ($existing==='') {
        $encoded=json_encode($pin);
        if (fwrite($handle,$encoded)!==strlen($encoded) || !fflush($handle)) { flock($handle,LOCK_UN); fclose($handle); respond(['ok'=>false,'error'=>'Nelze zabezpečit úložiště administrace.'],503); }
        @chmod($dir.'/admin-database.json',0600);
    } elseif (json_decode($existing,true)!==$pin) {
        flock($handle,LOCK_UN); fclose($handle);
        respond(['ok'=>false,'error'=>'Úložiště administrace se změnilo. Obnovte stránku.'],409);
    }
    flock($handle,LOCK_UN); fclose($handle);
}

function admin_session_user(): ?array {
    if (empty($_SESSION['admin_username']) || empty($_SESSION['admin_version']) || empty($_SESSION['admin_started'])) return null;
    if (time()-(int)$_SESSION['admin_started']>28800 || time()-(int)($_SESSION['admin_seen']??0)>1800) return null;
    $user=admin_user((string)$_SESSION['admin_username']);
    if (!$user || (int)$user['id']!==(int)($_SESSION['admin_id']??0) || !hash_equals($user['version'],(string)$_SESSION['admin_version'])) return null;
    $_SESSION['admin_seen']=time();
    return $user;
}

function require_admin(): void {
    if (!admin_session_user()) { admin_clear_session(); respond(['ok'=>false,'error'=>'Přihlášení vypršelo. Přihlaste se znovu.'],401); }
}

function require_csrf(): void {
    $given=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
    if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'],$given)) respond(['ok'=>false,'error'=>'Platnost stránky vypršela. Obnovte ji.'],403);
}

function admin_store_password(array $user, string $hash): bool {
    global $pdo;
    $q=$pdo->prepare('UPDATE admin_auth_state SET password_hash=?,version=? WHERE admin_id=? AND version=?');
    $q->execute([$hash,bin2hex(random_bytes(32)),$user['id'],$user['version']]);
    if ($q->rowCount()!==1) return false;
    // Retire the legacy verifier too; older deployed code must not accept the old password.
    $salt=bin2hex(random_bytes(16));
    $pdo->prepare('UPDATE admin_users SET password_salt=?,password_hash=?,updated_at=? WHERE id=?')->execute([$salt,bin2hex(random_bytes(32)),gmdate('c'),$user['id']]);
    $pdo->prepare('DELETE FROM admin_reset_tokens WHERE admin_id=?')->execute([$user['id']]);
    return true;
}

function admin_auth_dispatch(): void {
    global $pdo,$method,$action;
    if ($method==='GET' && $action==='session') {
        $user=admin_session_user();
        if (!$user) { $csrf=$_SESSION['csrf']??bin2hex(random_bytes(32)); $_SESSION=['csrf'=>$csrf]; }
        respond(['ok'=>true,'authenticated'=>(bool)$user,'username'=>$user['username']??null,'csrf'=>$_SESSION['csrf']]);
    }
    if ($method!=='POST') return;
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>8192) respond(['ok'=>false,'error'=>'Požadavek je příliš velký.'],413);
    $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
    if ($origin!=='' && $origin!=='https://kupsiapku.cz') respond(['ok'=>false,'error'=>'Nepovolený původ požadavku.'],403);
    require_csrf(); // Includes login and both reset steps.
    $data=json_input();
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
    if ($action==='login') {
        $username=clean_string($data,'username',80);
        admin_limit('login-ip',$ip,20,900);
        admin_limit('login-user',strtolower($username),20,900);
        $user=admin_user($username);
        if (!$user || !admin_verify((string)($data['password']??''),$user)) { usleep(250000); respond(['ok'=>false,'error'=>'Nesprávné přihlašovací údaje.'],401); }
        admin_pin_database();
        // Migrate legacy verifiers only after the password has been proved.
        if ($user['modern_hash']===null && strlen((string)$data['password'])<=72) {
            $hash=admin_password_hash((string)$data['password']);
            $pdo->prepare('UPDATE admin_auth_state SET password_hash=? WHERE admin_id=? AND version=? AND password_hash IS NULL')->execute([$hash,$user['id'],$user['version']]);
        }
        session_regenerate_id(true);
        $_SESSION=['admin_id'=>(int)$user['id'],'admin_username'=>$user['username'],'admin_version'=>$user['version'],'admin_started'=>time(),'admin_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];
        respond(['ok'=>true,'username'=>$user['username'],'csrf'=>$_SESSION['csrf']]);
    }
    if ($action==='logout') { admin_clear_session(); respond(['ok'=>true]); }
    if ($action==='forgot-password') {
        admin_limit('reset-ip',$ip,10,3600);
        $configFile=__DIR__.'/admin-config.php';
        $config=is_file($configFile) ? require $configFile : [];
        $email=(string)(getenv('ADMIN_RESET_EMAIL') ?: ($config['reset_email']??''));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/',$email)) respond(['ok'=>false,'error'=>'Obnova hesla zatím není nastavena na serveru.'],503);
        $username=clean_string($data,'username',80);
        admin_limit('reset-user',strtolower($username),3,3600);
        $user=$username==='admin' ? admin_user('admin') : null;
        if ($user) {
            $token=bin2hex(random_bytes(32));
            $digest=hash('sha256',$token);
            $pdo->prepare('DELETE FROM admin_reset_tokens WHERE expires_at < ?')->execute([time()]);
            $pdo->prepare('INSERT INTO admin_reset_tokens(token_hash,admin_id,version,expires_at) VALUES(?,?,?,?)')->execute([$digest,$user['id'],$user['version'],time()+1800]);
            // Fragment keeps the secret out of HTTP/access logs and Referer headers.
            $link='https://kupsiapku.cz/admin.html#reset='.$token;
            $body="Obnova hesla administrátora KupSiApku.cz\n\nOtevřete tento jednorázový odkaz do 30 minut:\n".$link."\n\nPokud jste obnovu nežádali, zprávu ignorujte. Heslo se zatím nezměnilo.\n";
            $sent=@mail($email,'=?UTF-8?B?'.base64_encode('Obnova hesla administrátora KupSiApku.cz').'?=',$body,"MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: Kup si apku <info@jirijanousek.cz>");
            if (!$sent) { $pdo->prepare('DELETE FROM admin_reset_tokens WHERE token_hash=?')->execute([$digest]); error_log('Admin reset mail transport failed.'); }
        }
        respond(['ok'=>true,'message'=>'Pokud je pro správce nastavena obnova, odkaz přijde na jeho ověřený e-mail. Zkontrolujte i spam.']);
    }
    if ($action==='reset-password') {
        admin_limit('reset-token-ip',$ip,20,900);
        $token=(string)($data['token']??'');
        $next=admin_new_password($data);
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) respond(['ok'=>false,'error'=>'Odkaz je neplatný nebo vypršel.'],422);
        $q=$pdo->prepare('SELECT t.*,u.username FROM admin_reset_tokens t JOIN admin_users u ON u.id=t.admin_id WHERE token_hash=? AND expires_at>?');
        $q->execute([hash('sha256',$token),time()]); $row=$q->fetch();
        $user=$row ? admin_user($row['username']) : null;
        if (!$user || !hash_equals($row['version'],$user['version'])) respond(['ok'=>false,'error'=>'Odkaz je neplatný nebo vypršel.'],422);
        admin_pin_database();
        $hash=admin_password_hash($next);
        $pdo->beginTransaction();
        try {
            // Claim the single-use token inside the same transaction as the password update.
            $q=$pdo->prepare('DELETE FROM admin_reset_tokens WHERE token_hash=? AND expires_at>?');
            $q->execute([hash('sha256',$token),time()]);
            if ($q->rowCount()!==1 || !admin_store_password($user,$hash)) { $pdo->rollBack(); respond(['ok'=>false,'error'=>'Odkaz je neplatný nebo vypršel.'],422); }
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        admin_clear_session(); respond(['ok'=>true,'message'=>'Heslo bylo obnoveno. Přihlaste se novým heslem.']);
    }
    if ($action==='change-password') {
        require_admin();
        admin_limit('change-password',(string)$_SESSION['admin_id'],10,900);
        $next=admin_new_password($data);
        $user=admin_session_user();
        if (!$user || !admin_verify((string)($data['currentPassword']??''),$user)) respond(['ok'=>false,'error'=>'Současné heslo není správné.'],422);
        $hash=admin_password_hash($next);
        $pdo->beginTransaction();
        try {
            if (!admin_store_password($user,$hash)) { $pdo->rollBack(); respond(['ok'=>false,'error'=>'Účet se změnil. Přihlaste se znovu.'],409); }
            $pdo->commit();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        admin_clear_session(); respond(['ok'=>true,'message'=>'Heslo bylo změněno a všechna přihlášení ukončena. Přihlaste se novým heslem.']);
    }
}
