"""Exercise registration and order persistence against a disposable MySQL database."""
from http.cookies import SimpleCookie
import json
import urllib.request

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
    with urllib.request.urlopen(urllib.request.Request(base + path, data=data, headers=headers), timeout=15) as response:
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
print('Checkout flow OK: register, session, create order, list persisted order')
