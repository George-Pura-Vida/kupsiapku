"""Exercise registration and order persistence against a disposable MySQL database."""
from http.cookies import SimpleCookie
import json
import os
import pathlib
import re
import subprocess
import urllib.request
import urllib.error

base = 'http://127.0.0.1:8080'
session_cookie = ''


def request(path, payload=None, csrf=None):
    global session_cookie
    data = json.dumps(payload).encode() if payload is not None else None
    headers = {'Accept': 'application/json'}
    if data is not None:
        headers['Content-Type'] = 'application/json'
    if csrf:
        headers['X-CSRF-Token'] = csrf
    if session_cookie:
        headers['Cookie'] = session_cookie
    try:
        response = urllib.request.urlopen(urllib.request.Request(base + path, data=data, headers=headers), timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        if response.headers.get('Set-Cookie'):
            cookie = SimpleCookie()
            cookie.load(response.headers['Set-Cookie'])
            if 'ksa_session' in cookie:
                session_cookie = 'ksa_session=' + cookie['ksa_session'].value
        return response.status, json.load(response)


status, registered = request('/api/auth/register.php', {
    'firstName': 'Test', 'lastName': 'Checkout', 'email': 'checkout@example.test',
    'password': 'test-password-only',
})
assert status == 201 and registered['ok'], registered
status, me = request('/api/auth/me.php')
assert status == 200 and me['authenticated'] and me['user']['email'] == 'checkout@example.test', me
status, created = request('/api/orders/create.php', {
    'apps': ['zdravi', 'finance', 'portfolio'], 'currency': 'CZK',
    'firstName': 'Test', 'lastName': 'Checkout', 'email': 'checkout@example.test',
    'street': 'Testovací 1', 'city': 'Praha', 'zip': '15000', 'country': 'Česká republika',
    'recurring': 'on', 'terms': 'on', 'privacy': 'on', 'digital': 'on',
}, me['csrfToken'])
assert status == 201 and created['ok'], created
order = created['order']
assert order['totalMinor'] == 129000 and order['itemCount'] == 3 and order['currency'] == 'CZK', order
status, listed = request('/api/orders/list.php')
assert status == 200 and listed['ok'] and len(listed['orders']) == 1, listed
assert listed['orders'][0]['number'] == order['number'] and listed['orders'][0]['totalMinor'] == 129000, listed

# Order creation now sends customer/admin notifications through the same captured
# sendmail transport. Clear those messages before testing password-reset privacy.
mail_path = pathlib.Path('/tmp/checkout-reset-mail.txt')
mail_path.unlink(missing_ok=True)

status, forgot = request('/api/auth/forgot.php', {'email': 'absent@example.test'})
assert status == 200 and forgot['ok']
assert not mail_path.exists(), 'Unknown account must not receive mail'
status, forgot = request('/api/auth/forgot.php', {'email': 'checkout@example.test'})
assert status == 200 and forgot['ok'] and mail_path.exists(), forgot
mail = mail_path.read_text()
token = re.search(r'#reset=([a-f0-9]{64})', mail)[1]
def sql(query):
    result = subprocess.run(['php', '-r', 'require "api/auth.php"; $pdo=db(true); echo $pdo->query(' + repr(query) + ')->fetchColumn();'], capture_output=True, text=True, check=True)
    return result.stdout
assert sql('SELECT token_hash FROM auth_password_resets LIMIT 1') != token
payload = {'token': token, 'newPassword': 'new-test-password', 'confirmPassword': 'new-test-password'}
status, expired = request('/api/auth/reset.php', {**payload, 'token': '0'*64})
assert status == 422 and expired['error'] == 'INVALID_TOKEN', expired
status, reset = request('/api/auth/reset.php', payload)
assert status == 200 and reset['ok'], reset
status, reused = request('/api/auth/reset.php', payload)
assert status == 422 and reused['error'] == 'INVALID_TOKEN', reused
status, me = request('/api/auth/me.php')
assert status == 200 and not me['authenticated'], me
status, old = request('/api/auth/login.php', {'email':'checkout@example.test','password':'test-password-only'})
assert status == 401, old
status, new = request('/api/auth/login.php', {'email':'checkout@example.test','password':'new-test-password'})
assert status == 200 and new['ok'], new
print('Checkout and customer reset OK: registered account, persisted order, private single-use token, revoked session, new login')
