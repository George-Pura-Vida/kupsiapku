# Administrator authentication

`admin.html` and `/api/admin.php` use only `admin_users` plus `admin_auth_state`; ordinary user and affiliate credentials do not grant administrator access.

The existing salted PBKDF2 verifier remains readable for migration. A successful login upgrades it to Argon2id where supported (bcrypt cost 12 otherwise). New passwords are 12+ characters and at most 72 UTF-8 bytes. A password change/reset replaces the legacy verifier with random unusable bytes. No fixed account or password is seeded. Existing records are retained.

Sessions use a Secure, HttpOnly, SameSite=Strict cookie, rotate on login, expire after 30 minutes idle/eight hours absolute, and are checked against the database credential version on every protected request. Changing/resetting the password revokes all sessions and outstanding reset tokens. All POST requests, including login and recovery, require the session's CSRF token. Rate limits are stored server-side, not in cookies.

The first proved login or valid reset pins the existing database in `api/data/admin-database.json`. After that, authentication cannot silently switch from MySQL to SQLite or another configured MySQL database. Back up and preserve this file with the database. A database relocation requires deliberate server-side migration of both the account and its pin. Do not restore the old fixed seed.

## Recovery setup (server only)

Set `ADMIN_RESET_EMAIL` in the existing private server environment, or create `api/admin-config.php` containing `<?php return ['reset_email' => 'YOUR_VERIFIED_ADDRESS'];`. The latter file is denied by `.htaccess` and excluded from Git and deployment. Never commit the real recovery address or credentials. This single-owner recovery mapping applies only to username `admin`; additional administrators need a separately verified per-account mapping.

Recovery uses the hosting's existing PHP mail transport and sender `info@jirijanousek.cz`. The API gives a generic response, including for unknown usernames. Transport failures are logged without addresses or tokens and their tokens are removed. An accepted mail is not proof of inbox delivery; verify delivery before relying on recovery. Missing recovery configuration returns a clear service-unavailable response.

Links contain 256-bit random tokens in a URL fragment, removed immediately by the UI. Only their SHA-256 digests are stored. Tokens expire after 30 minutes, are bound to the current credential version, and are atomically consumed with the password update. Visiting a link does not change a password.

## Verification and deployment

Run `python3 tests/admin_auth_test.py` with PHP 8.1+ and pdo_sqlite/mbstring installed (`PHP_BIN` and JSON `PHP_ARGS` can override the runtime). The integration test creates an isolated temporary database and loopback-only mail capture; it never uses production data or sends external mail. The existing main-branch FTP workflow runs these tests before deployment and checks the public admin page/session and anonymous dashboard rejection afterward.

Then test the owner's production login, change password (automatic logout), login with the new password, explicit logout/login, recovery email, reset, and new login. The owner must enter private passwords and use the delivered recovery link. Do not store these in repository files, logs or screenshots. Preserve the actual hosting bootstrap and data; a Git-only copy predating the admin backend is insufficient.
