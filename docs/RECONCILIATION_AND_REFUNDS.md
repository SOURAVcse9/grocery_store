# GroCo Grocery Store — Financial Reconciliation & Refund Management

## 1. Reconciliation Engine Architecture

The administrative reconciliation engine (`admin/payments/reconciliation.php`) operates continuously to detect discrepancies across three data planes:
1. **SSLCOMMERZ Gateway Ledger** (Queried via Transaction Query API)
2. **Internal Payments Table** (`payments`)
3. **Storefront Orders Table** (`orders`)

### Anomaly Classifications & Auto-Detection Queries

#### 1. Status Mismatch
- **Condition**: `payments.status = 'PAID'` while `orders.payment_status != 'paid'`.
- **Cause**: Network failure occurring between payment confirmation and order table update.
- **Resolution**: Click "Sync" on the reconciliation screen to invoke `PaymentService::syncTransactionStatus($tranId)` and align order status.

#### 2. Amount Mismatch
- **Condition**: `(p.verification_status = 'AMOUNT_MISMATCH' OR ABS(p.amount - o.total_amount) >= 0.01) AND p.status IN ('PAID', 'REVIEW_REQUIRED')`.
- **Cause**: Client underpayment attempt or altered checkout totals.
- **Resolution**: Transaction locked for fraud inspection. Admin can review gateway audit logs and cancel fraudulent orders.

#### 3. Stale In-Flight Pending
- **Condition**: `payments.status IN ('INITIATED', 'PENDING') AND p.created_at < (NOW() - INTERVAL 30 MINUTE)`.
- **Cause**: Customer abandoned payment portal without clicking cancel.
- **Resolution**: One-click "Check Gateway" queries the merchant API. If user paid, finalizes order; otherwise marks as `EXPIRED`.

#### 4. Missing IPNs
- **Condition**: `payments.status = 'PAID' AND payments.ipn_received = 0`.
- **Cause**: Temporary gateway IPN delivery delay or firewall restriction.
- **Resolution**: Verified automatically when gateway sends retry, or marked resolved upon query validation.

---

## 2. Refund Workflow (Full & Partial)

GroCo integrates with the official SSLCOMMERZ Refund API:

```
GET /validator/api/merchantTransIDvalidationAPI.php?
    refund_trn_id={unique_id}&
    bank_tran_id={bank_tran_id}&
    refund_amount={amount}&
    refund_remarks={remarks}&
    store_id={store_id}&
    store_passwd={store_passwd}&
    v=1&format=json
```

### Refund Business Logic Rules

1. **Eligibility Constraint**:
   Only payments in `PAID` or `PARTIALLY_REFUNDED` status with an existing `bank_tran_id` can be refunded.
2. **Over-Refund Barrier**:
   The engine computes `maxRefundable = round($paidAmount - $alreadyRefunded, 2)`. Attempting to refund any amount greater than `maxRefundable` is blocked immediately with status `OVER_REFUND`.
3. **State Updates**:
   - If `newRefundedAmount >= totalAmount`:
     - `payments.status` &rarr; `REFUNDED`
     - `orders.payment_status` &rarr; `refunded`
   - If `newRefundedAmount < totalAmount`:
     - `payments.status` &rarr; `PARTIALLY_REFUNDED`
     - `orders.payment_status` &rarr; `partially_refunded`
4. **General Ledger Integration**:
   An expense record is automatically inserted into `transactions` to ensure double-entry accounting reconciliation.
