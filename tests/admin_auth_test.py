"""Isolated HTTP integration test. Never connects to production or sends real mail.
Usage: PHP_BIN=/path/to/php PHP_ARGS='["-d", "extension=pdo_sqlite"]' python tests/admin_auth_test.py
"""
import hashlib, http.client, json, os, pathlib, re, secrets, shutil, socket, socketserver, subprocess, sys, tempfile, threading, time

ROOT = pathlib.Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BIN', 'php')
ARGS = json.loads(os.environ.get('PHP_ARGS', '[]'))
messages = []

class SMTP(socketserver.StreamRequestHandler):
    def handle(self):
        self.wfile.write(b'220 localhost test\r\n')
        while line := self.rfile.readline():
            command = line[:4].upper()
            if command == b'DATA':
                self.wfile.write(b'354 continue\r\n')
                data = b''
                while (part := self.rfile.readline()) not in (b'.\r\n', b''):
                    data += part
                messages.append(data.decode())
                self.wfile.write(b'250 stored\r\n')
            elif command == b'QUIT':
                self.wfile.write(b'221 bye\r\n'); break
            else:
                self.wfile.write(b'250 OK\r\n')

class Client:
    def __init__(self, port): self.port, self.cookie, self.csrf = port, '', ''
    def call(self, action, data=None, csrf=True, origin='https://kupsiapku.cz'):
        conn=http.client.HTTPConnection('127.0.0.1',self.port,timeout=20)
        headers={'Cookie':self.cookie, 'Origin':origin}
        if data is not None:
            headers['Content-Type']='application/json'
            if csrf: headers['X-CSRF-Token']=self.csrf
        conn.request('GET' if data is None else 'POST','/api/admin.php?action='+action,None if data is None else json.dumps(data),headers)
        res=conn.getresponse(); raw=res.read(); status=res.status
        for k,v in res.getheaders():
            if k.lower()=='set-cookie':
                assert 'secure' in v.lower() and 'httponly' in v.lower() and 'samesite=strict' in v.lower()
                self.cookie=v.split(';')[0]
        conn.close()
        try: out=json.loads(raw)
        except Exception: raise AssertionError(f'Non-JSON HTTP {status}: {raw[:300]!r}')
        if out.get('csrf'): self.csrf=out['csrf']
        return status,out
    def session(self): return self.call('session')
    def login(self,password): self.session(); return self.call('login',{'username':'admin','password':password})

def check(response,status):
    assert response[0]==status, response
    return response[1]

with tempfile.TemporaryDirectory(prefix='ksa-auth-test-') as temp, socketserver.TCPServer(('127.0.0.1',0),SMTP) as smtp:
    temp=pathlib.Path(temp)
    shutil.copytree(ROOT/'api',temp/'api',ignore=shutil.ignore_patterns('*.sqlite','admin-config.php'))
    (temp/'api/data').mkdir(exist_ok=True)
    (temp/'api/admin-config.php').write_text("<?php return ['reset_email'=>'admin@example.test'];",encoding='utf8')
    secret='Test-'+secrets.token_hex(12)
    new='Next-'+secrets.token_hex(12)
    reset='Reset-'+secrets.token_hex(12)
    salt=secrets.token_hex(16)
    legacy=hashlib.pbkdf2_hmac('sha256',secret.encode(),bytes.fromhex(salt),120000).hex()
    seed="<?php require __DIR__.'/api/bootstrap.php'; $pdo=db(); $pdo->prepare('INSERT INTO admin_users(username,password_salt,password_hash,updated_at) VALUES(?,?,?,?)')->execute(['admin',"+repr(salt)+","+repr(legacy)+",gmdate('c')]);"
    (temp/'seed.php').write_text(seed)
    env={k:v for k,v in os.environ.items() if not k.startswith(('DB_','ADMIN_'))}
    subprocess.run([PHP,*ARGS,str(temp/'seed.php')],env=env,check=True,capture_output=True)
    threading.Thread(target=smtp.serve_forever,daemon=True).start()
    with socket.socket() as s: s.bind(('127.0.0.1',0)); port=s.getsockname()[1]
    log=open(temp/'server.log','w+')
    transport=['-d','SMTP=127.0.0.1','-d',f'smtp_port={smtp.server_address[1]}']
    if os.name!='nt':
        sender=temp/'sendmail.py'
        sender.write_text('import smtplib,sys\ns=smtplib.SMTP("127.0.0.1",'+str(smtp.server_address[1])+')\ns.sendmail("test@example.test","admin@example.test",sys.stdin.buffer.read())\ns.quit()\n')
        transport=['-d',f'sendmail_path={sys.executable} {sender}']
    process=subprocess.Popen([PHP,*ARGS,*transport,'-d','display_errors=0','-S',f'127.0.0.1:{port}','-t',str(temp)],env=env,stdout=log,stderr=log)
    try:
        c=Client(port)
        for _ in range(100):
            try: check(c.session(),200); break
            except ConnectionError: time.sleep(.05)
        else: raise AssertionError('PHP server did not start')
        check(c.call('dashboard'),401)
        c.session()
        check(c.call('login',{'username':'admin','password':secret},csrf=False),403)
        check(c.call('login',{'username':'admin','password':secret},origin='https://evil.example'),403)
        check(c.login('wrong-password'),401)
        before=c.cookie
        check(c.login(secret),200)
        assert json.loads((temp/'api/data/admin-database.json').read_text())['driver']=='sqlite'
        assert c.cookie!=before
        assert check(c.session(),200)['authenticated']
        other=Client(port); check(other.login(secret),200)
        def change(current,next,confirm=None):
            return c.call('change-password',{'currentPassword':current,'newPassword':next,'confirmPassword':next if confirm is None else confirm})
        check(change('wrong',new),422)
        check(change(secret,'short'),422)
        check(change(secret,new,'mismatch'),422)
        check(change(secret,new),200)
        assert not check(other.session(),200)['authenticated']
        assert not check(c.session(),200)['authenticated']
        check(c.login(secret),401)
        check(c.login(new),200)
        check(c.call('logout',{}),200)
        assert not check(c.session(),200)['authenticated']
        check(c.login(new),200)
        check(c.call('forgot-password',{'username':'unknown'}),200)
        assert len(messages)==0
        check(c.call('forgot-password',{'username':'admin'}),200)
        assert len(messages)==1
        token=re.search(r'#reset=([a-f0-9]{64})',messages[-1])[1]
        import sqlite3
        db=sqlite3.connect(temp/'api/data/kupsiapku.sqlite')
        assert db.execute('SELECT token_hash FROM admin_reset_tokens').fetchone()[0]==hashlib.sha256(token.encode()).hexdigest()
        modern=db.execute('SELECT password_hash FROM admin_auth_state').fetchone()[0]
        assert modern.startswith(('$argon2id$', '$2y$')) and modern!=new
        anonymous=Client(port); anonymous.session()
        payload={'token':token,'newPassword':reset,'confirmPassword':reset}
        check(anonymous.call('reset-password',{**payload,'token':'0'*64}),422)
        db.execute('UPDATE admin_reset_tokens SET expires_at=0');db.commit()
        check(anonymous.call('reset-password',payload),422)
        check(c.call('forgot-password',{'username':'admin'}),200)
        token=re.search(r'#reset=([a-f0-9]{64})',messages[-1])[1]; payload['token']=token
        check(anonymous.call('reset-password',payload),200)
        assert not check(c.session(),200)['authenticated']
        anonymous.session(); check(anonymous.call('reset-password',payload),422)
        check(c.login(new),401); check(c.login(reset),200)
        assert db.execute('SELECT COUNT(*) FROM admin_reset_tokens').fetchone()[0]==0
        for _ in range(22): response=c.login('incorrect')
        check(response,429)
        db.close()
        print('PASS: login, CSRF/origin, secure cookies, legacy migration, password validation/change, logout, session revocation, reset delivery, hashed/expired/single-use tokens, new login and rate limiting.')
    finally:
        process.terminate(); process.wait(timeout=10); smtp.shutdown(); log.close()
