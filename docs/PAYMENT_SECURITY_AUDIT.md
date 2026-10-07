# GroCo Grocery Store — Payment Security & Defensive Engineering Audit

## 1. Security Threat Model & Defense Mechanisms

| Attack Vector | Vulnerability Description | GroCo Architectural Defense |
|---|---|---|
| **Price Tampering** | Malicious client alters `$_POST['amount']` during checkout submission. | Server recalculates the total from database product prices and active coupons. Client-submitted prices are discarded. |
| **Fake Success Spoofing** | Attacker manually navigates to `success.php` with spoofed GET/POST query parameters. | `success.php` **NEVER** trusts the client. It calls the SSLCOMMERZ Order Validation API using `val_id` server-to-server. |
| **Amount Disparity / Underpayment** | Attacker initiates a 1,200 BDT order, manipulates gateway session to pay 1 BDT, and completes checkout. | The validation engine compares `validatedAmount` vs `expectedAmount`. If `abs($v - $e) >= 0.01`, order is **LOCKED** to `REVIEW_REQUIRED`, flagged `AMOUNT_MISMATCH`, and never marked `PAID`. |
| **Currency Arbitrage** | Attacker attempts to pay in USD/EUR instead of BDT. | Gateway response currency is compared strictly against `payments.currency` (`BDT`). Any mismatch triggers immediate rejection. |
| **Race Condition / Double Spend** | Simultaneous arrival of browser callback and IPN worker thread causing duplicate ledger or inventory action. | Pessimistic database row locks (`SELECT ... FOR UPDATE`) serialize execution. The second worker detects `status === 'PAID'` and exits idempotently. |
| **Double Stock Deduction** | Stock decremented during checkout, and decremented again upon payment validation. | `orders.inventory_deducted` boolean flag ensures stock is decremented exactly once across the order lifetime. |
| **Secret Credential Exposure** | Merchant `store_passwd` exposed in HTML, client JavaScript, or unredacted database logs. | `SSLCommerzService::redactCredentials()` automatically replaces passwords, card numbers, and CVVs with `[REDACTED]` prior to logging. |
| **Cross-Site Request Forgery (CSRF)** | Unauthorized order placement or payment retry triggered via third-party form. | Synchronizer token pattern (`verify_csrf_or_fail()`) enforced on all customer-initiated checkout and retry requests. |
| **Insecure Direct Object Reference (IDOR)** | User attempts to retry payment or view invoice for another customer's order. | Customer ID is matched against `current_user_id()` on all private customer endpoints. |

---

## 2. Row-Level Locking Implementation

Pessimistic concurrency control in `PaymentService::validateAndFinalizePayment`:

```php
// 1. Lock payment record
$stmt = $this->pdo->prepare('SELECT * FROM payments WHERE tran_id = :tid FOR UPDATE');
$stmt->execute(['tid' => trim($tranId)]);
$payment = $stmt->fetch();

// 2. Lock order record
$orderStmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = :id FOR UPDATE');
$orderStmt->execute(['id' => $orderId]);
$order = $orderStmt->fetch();
```

By disabling query emulation (`PDO::ATTR_EMULATE_PREPARES => false`), MariaDB executes true transactional row locks, ensuring that concurrent callback threads wait until the first transaction commits or rolls back.

---

## 3. Secret Redaction Strategy

Both `payment_audit_logs` and `payment_webhook_events` pass raw payloads through `SSLCommerzService::redactCredentials()`:

```php
public static function redactCredentials(array $data): array
{
    $redacted = $data;
    $sensitiveKeys = ['store_passwd', 'password', 'card_no', 'cvv', 'card_cvv', 'secret'];

    foreach ($sensitiveKeys as $k) {
        if (isset($redacted[$k])) {
            $redacted[$k] = '[REDACTED]';
        }
    }

    return $redacted;
}
```
This guarantees that merchant secrets and cardholder primary account numbers (PAN) are never stored in plaintext within database logs or application traces.
