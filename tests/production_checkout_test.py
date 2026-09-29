"""One-shot, explicitly authorized production order probe; no payment is made."""
from http.cookiejar import CookieJar
from urllib.error import HTTPError
from urllib.request import HTTPCookieProcessor, Request, build_opener
import json
import secrets
import time

base = 'https://kupsiapku.cz'
email = f'qa-checkout-{int(time.time())}@example.invalid'
client = build_opener(HTTPCookieProcessor(CookieJar()))


def call(path, payload=None, csrf=None):
    body = json.dumps(payload).encode() if payload is not None else None
    headers = {'Accept': 'application/json', 'Origin': base}
    if body is not None:
        headers['Content-Type'] = 'application/json'
    if csrf:
        headers['X-CSRF-Token'] = csrf
    try:
        with client.open(Request(base + path, data=body, headers=headers), timeout=30) as response:
            return response.status, json.load(response)
    except HTTPError as error:
        raise AssertionError(f'{path}: HTTP {error.code}, {error.read().decode()[:500]}') from error


status, registered = call('/api/auth/register.php', {
    'firstName': 'TEST', 'lastName': 'OBJEDNAVKA', 'email': email,
    'password': secrets.token_urlsafe(32),
})
assert status == 201 and registered.get('ok'), registered
status, me = call('/api/auth/me.php')
assert status == 200 and me.get('authenticated') and me['user']['email'] == email, me
status, created = call('/api/orders/create.php', {
    'apps': ['zdravi'], 'currency': 'CZK',
    'firstName': 'TEST', 'lastName': 'OBJEDNAVKA', 'email': email,
    'street': 'Testovací 1', 'city': 'Praha', 'zip': '15000', 'country': 'Česká republika',
    'recurring': 'on', 'terms': 'on', 'privacy': 'on', 'digital': 'on',
}, me['csrfToken'])
assert status == 201 and created.get('ok'), created
order = created['order']
assert order['totalMinor'] == 50000 and order['currency'] == 'CZK' and order['status'] == 'pending', order
status, listed = call('/api/orders/list.php')
assert status == 200 and listed.get('ok'), listed
assert any(row['number'] == order['number'] and row['totalMinor'] == 50000 for row in listed['orders']), listed
print(f'PRODUCTION ORDER VERIFIED: {order["number"]}, 500 CZK, pending; visible in customer API; test email {email}')
