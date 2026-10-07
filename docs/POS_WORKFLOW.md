# GroCo Supermarket POS — Workflow & Lifecycle Specification (2026)

## 1. Cashier Daily Operational Lifecycle

```
[Start of Shift]
       │
       ▼
1. Open Cash Drawer (register.php / Initialize Shift)
   - Select Store, Register & Terminal
   - Enter Opening Drawer Float (e.g. ৳1,000.00)
   - Shift status -> 'open'
       │
       ▼
2. Checkout Operations (pos/index.php)
   - Scan Barcode (< 50ms) / Search Catalog (F1/F2)
   - Select Weighted Produce (+0.25, +0.5, +1.0 kg)
   - Search/Bind Customer Phone (F3) / New Customer (F4)
   - Apply Line or Cart Discounts (F7)
   - Hold / Park Cart if customer forgot wallet (F5)
   - Resume Parked Cart (F6 / hold-orders.php)
       │
       ▼
3. Payment Execution (F9 / Split Payment Modal)
   - Select Payment Tenders (Cash, Card, bKash, Nagad, Rocket, Wallet, Bank)
   - Quick Cash Presets ([Exact], [+৳50], [+৳100], [+৳500], [+৳1000])
   - Calculate Change Due & Confirm Sale (F10)
       │
       ▼
4. Receipt & Slip Printing
   - Auto-print 80mm / 58mm / A4 thermal receipt
   - Clear cart & refocus scanner for next customer
       │
       ▼
5. Mid-Shift Operations
   - Petty Cash In / Out (F8) (Change replenishment, tea/snacks)
   - Returns & Refunds Processing (Undo modal)
   - Mid-shift X-Reading generation
       │
       ▼
6. End of Shift Closing (register.php)
   - Count physical cash drawer
   - Submit Actual Cash for Z-Reading
   - Reconcile Discrepancy (Overage / Shortage / Balanced)
   - Shift status -> 'closed'
```

## 2. Speed Optimization (< 50ms Barcode Response)

- Catalog products are loaded into client-side DOM memory on initialization.
- Scanner keystrokes are captured globally using input interval threshold (<50ms).
- Exact SKU/Barcode matches bypass dropdowns and append to cart instantaneously.
