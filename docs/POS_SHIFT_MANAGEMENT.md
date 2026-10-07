# GroCo Supermarket POS — Shift Management & Cash Reconciliation (2026)

## 1. Cash Drawer Shift Lifecycle

```
+-------------------------------------------------------------------------------+
| 1. Initialize Shift                                                           |
|    - Cashier enters opening cash drawer float (e.g. ৳1,000.00).              |
|    - Shift status set to 'open' with timestamp in pos_shifts.                 |
+-------------------------------------------------------------------------------+
                                      │
                                      ▼
+-------------------------------------------------------------------------------+
| 2. Petty Cash Movements                                                       |
|    - Cash In (Float addition): Recorded in pos_cash_movements.                |
|    - Cash Out (Drawer drop / Expenses): Recorded with reason and authorization.|
+-------------------------------------------------------------------------------+
                                      │
                                      ▼
+-------------------------------------------------------------------------------+
| 3. Mid-Shift X-Reading (Reading without closing)                              |
|    - Summarizes cash sales, card sales, MFS sales, cash in, cash out.         |
|    - Displays current expected cash balance in drawer.                        |
+-------------------------------------------------------------------------------+
                                      │
                                      ▼
+-------------------------------------------------------------------------------+
| 4. End-of-Shift Z-Reading & Cash Reconciliation                               |
|    - Expected Cash = Opening Float + Cash Sales + Cash In - Cash Out - Refunds|
|    - Cashier enters Counted / Actual Physical Cash in drawer.                 |
|    - System computes Discrepancy = Actual Cash - Expected Cash.               |
|    - Identifies Overage (> 0), Shortage (< 0), or Balanced (= 0).             |
|    - Closes shift (status = 'closed') and archives reconciliation record.     |
+-------------------------------------------------------------------------------+
```
