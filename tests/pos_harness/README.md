# POS test harness  (development only — NOT part of the running application)

**Never run any of this against a production database.** It creates, sells, refunds and voids test data.

What is here
- `test_schema.sql`   — a schema *reconstructed from the SQL found in admin/* (the real schema was not supplied). Compare it with your real DB before trusting results.
- `stub_dbconnect.php`— minimal stand-in for the main project's `public/dbconnect.php` (db(), input(), CSRF, flash …). Copy it to `public/dbconnect.php` of a **scratch checkout** only. Set `TEST_DB_USER` / `TEST_DB_PASS` env vars.
- `client.py`         — tiny HTTP client (log in, CSRF-aware JSON posts).
- `regression.py`     — 89 checks: login→scan→cart→checkout→inventory→receipt, server-side integrity, multi-payment, VAT, wallet, idempotency, concurrency, shifts, hold/resume, returns/void, receipts/reprint, security probes, key page loads.
- `browser_smoke.js`  — 26 checks that run the real `pos.js` + POS page in jsdom (`npm i jsdom@22`): scanning, qty edit, XSS escaping, F-keys, hold, UI checkout.

Run (scratch DB + `php -S 127.0.0.1:8099 -t <scratch-root>`): `python3 regression.py`
Users expected: `root` (Super Admin), `cashier`, `manager`, password `Pass#123` (test data only).
