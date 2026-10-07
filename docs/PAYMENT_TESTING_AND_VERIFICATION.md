# GroCo Grocery Store — Payment Testing & Production Verification Guide

## 1. Automated Test Suite Execution

The payment subsystem includes a dedicated verification matrix covering 24 distinct scenarios (41 assertions):

```bash
# Run the automated Payment Security Matrix test suite
php tests/payment_security_matrix_test.php
```

### Coverage Matrix Summary (TEST 01 to TEST 24)

| Test ID | Test Scenario | Verified Behavior |
|---|---|---|
| **TEST 01** | Configuration & Service Bootstrap | Validates `.env` loader, Store ID, and default currency `BDT`. |
| **TEST 02** | Transaction ID Format & Uniqueness | Ensures generated `tran_id` adheres to `<= 30 chars` and 100% uniqueness. |
| **TEST 03** | Zero-Float Monetary Precision | Verifies 0.01 tolerance comparison without floating-point errors. |
| **TEST 04** | Gateway Payload Structure | Validates all official V4 required session parameters. |
| **TEST 05** | Order & Payment Creation | Verifies `payments` record insertion and initial `PENDING` state. |
| **TEST 06** | Order Validation API | Verifies server-to-server validation using `val_id` and transition to `PAID`. |
| **TEST 07** | Amount Mismatch Defense | Rejects underpayment attacks; flags `AMOUNT_MISMATCH` and `REVIEW_REQUIRED`. |
| **TEST 08** | Currency Mismatch Defense | Rejects payments declaring non-BDT currencies (e.g. `USD`). |
| **TEST 09** | State Transition Idempotency | Prevents late `FAILED`/`CANCELLED` notifications from regressing a `PAID` order. |
| **TEST 10** | Concurrent Callback & IPN Handling | Serializes execution with row locks; acknowledges duplicate callback cleanly. |
| **TEST 11** | Inventory Finalization Idempotency| Verifies `inventory_deducted` flag prevents double inventory decrement. |
| **TEST 12** | Sessionless IPN Execution | Confirms webhook executes cleanly with zero `$_SESSION` dependencies. |
| **TEST 13** | Webhook Replay Protection | Logs raw inbound webhooks with SHA-256 payload hashes. |
| **TEST 14** | Payment Failure Handling | Handles bank decline and preserves order for customer retry. |
| **TEST 15** | Customer Cancellation | Handles user cancellation on checkout portal cleanly. |
| **TEST 16** | Partial Refund Execution | Processes partial refund and sets state to `PARTIALLY_REFUNDED`. |
| **TEST 17** | Full Refund Execution | Refunds balance to completion and transitions order to `refunded`. |
| **TEST 18** | Over-Refund Prevention | Blocks any refund exceeding the original net authorized amount. |
| **TEST 19** | Secret Redaction | Verifies `store_passwd` and PANs are redacted as `[REDACTED]`. |
| **TEST 20** | Transaction Query API Sync | Synchronizes local state using merchant transaction ID query API. |
| **TEST 21** | Customer Payment Retry | Generates a fresh unique `tran_id` on payment retry attempts. |
| **TEST 22** | Cash on Delivery Compatibility | Proves COD checkout remains 100% operational with zero regressions. |
| **TEST 23** | Reconciliation Anomaly Engine | Detects status and amount disparities between ledger and orders. |
| **TEST 24** | End-to-End File Integrity | Verifies deployment of all controllers, return hooks, and admin interfaces. |

---

## 2. Platform Regression Test Verification

Run all standard GroCo regression suites to verify holistic system stability:

```bash
# 1. Payment Security Matrix Test Suite (41/41 Passed)
php tests/payment_security_matrix_test.php

# 2. Production Readiness & Deployment Suite (64/64 Passed)
php tests/production_readiness_test.php

# 3. Modernization Services Verification Suite (14/14 Passed)
php tests/modernization_services_test.php

# 4. POS V2 Full Regression Safety Suite (7/7 Passed)
php tests/pos_v2_regression_test.php
```

---

## 3. Production Deployment & Go-Live Checklist

When transitioning from Sandbox to Live production credentials:

1. **Update `.env`**:
   - `PAYMENT_ENV=production`
   - `SSLCOMMERZ_IS_SANDBOX=false`
   - `SSLCOMMERZ_STORE_ID=your_real_production_store_id`
   - `SSLCOMMERZ_STORE_PASSWORD=your_real_production_store_password`
   - `SSLCOMMERZ_SUCCESS_URL=https://yourdomain.com/public/payment/sslcommerz/success.php`
   - `SSLCOMMERZ_FAIL_URL=https://yourdomain.com/public/payment/sslcommerz/fail.php`
   - `SSLCOMMERZ_CANCEL_URL=https://yourdomain.com/public/payment/sslcommerz/cancel.php`
   - `SSLCOMMERZ_IPN_URL=https://yourdomain.com/public/payment/sslcommerz/ipn.php`
2. **Whitelist Server IP with SSLCOMMERZ**:
   - Provide your live production web server public IP to SSLCOMMERZ support team for validation server whitelisting.
3. **Configure SSL / HTTPS**:
   - Production webhooks require a valid SSL certificate (HTTPS).
4. **IPN URL Registration in SSLCOMMERZ Merchant Portal**:
   - Log in to https://merchant.sslcommerz.com/
   - Navigate to **Integration Settings** &rarr; **IPN Settings**.
   - Set IPN URL to: `https://yourdomain.com/public/payment/sslcommerz/ipn.php` with HTTP POST enabled.
