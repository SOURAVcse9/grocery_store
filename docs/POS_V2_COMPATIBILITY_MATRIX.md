# GroCo POS V2 — Compatibility & Evolution Matrix

## 1. Feature-by-Feature Evolution Mapping

Every existing POS feature is preserved, hardened, and modernized without deleting or breaking existing operational workflows:

| # | Existing POS Feature | POS V2 Evolution | Backward Compatibility Guarantee |
| :- | :--- | :--- | :--- |
| **1** | Barcode Scanner Lookup | Continuous auto-focus, sub-50ms keystroke wedge listener, local catalog match before network query | Existing USB and Bluetooth scanners work out-of-the-box with zero configuration changes |
| **2** | Catalog Search | Fast debounced indexed search by Name, SKU, Barcode, Category, and Brand | Existing AJAX endpoints (`ajax/search_products.php`) remain intact |
| **3** | Product Catalog Grid | Touch-friendly responsive card grid with stock counters and produce weight indicators | Compatible with both touchscreens and mouse/keyboard navigation |
| **4** | Shopping Cart | Multi-unit decimal support (`kg`, `gm`, `liter`, `ml`, `pcs`, `pack`, `box`, `bottle`, `dozen`) with quick scale buttons (`+0.25`, `+0.5`, `+1.0 kg`) | Reuses existing `products.unit` database column; no duplicate unit schemas |
| **5** | Price Override | In-line price editing guarded by `pos.override` permission and supervisor reason prompt | Price modifications are recorded with audit logs and original regular prices |
| **6** | Customer Identification | Instant phone lookup, auto-selection on exact phone match, loyalty reward points and wallet balances display | Default Walk-in customer (`00000000000`) preserved as default fallback |
| **7** | Quick Customer Creation | Fast customer registration modal directly in checkout counter | Records directly into shared `users` table |
| **8** | Split Payments | Multi-tender checkout supporting Cash, Card (Visa/MasterCard/Amex/Nexus), Mobile Banking (bKash/Nagad/Rocket), Customer Wallet, and Bank Transfer | Single-method cash sales remain 1-click fast |
| **9** | Quick Cash Presets | Smart 1-touch tender buttons (`[Exact Total]`, `[+৳50]`, `[+৳100]`, `[+৳500]`, `[+৳1000]`, `[Round to 100]`) | Eliminates manual typing for standard paper note denominations |
| **10** | Hold & Resume Carts | Park active carts with reference notes; resume from header badge or dedicated list | Persisted in `pos_suspended_carts` with cashier and terminal identifiers |
| **11** | Shift Management | Cash register drawer lifecycle (`pos_shifts`), opening float recording, X-Reading mid-shift checks, closing Z-Reading reconciliation | Legacy `pos_drawer_transactions` kept synchronized with `pos_cash_movements` |
| **12** | Petty Cash Movements | Cash-In (float replenishment) and Cash-Out (expenses / drawer drops) with reason tracking | Automatically posted to general ledger (`transactions`) |
| **13** | Returns & Refunds | Itemized return processor validating against original invoice; restocks inventory and logs refund accounting | Original sales records are immutable and never deleted |
| **14** | Thermal Receipt Printing | 80mm thermal, 58mm thermal, and A4 invoice formats with barcode string, tax breakdown, and reprint audit | Compatible with all ESC/POS standard thermal printers |
| **15** | Offline Mode & Sync | LocalStorage outbox queue (`groco_pos_offline_queue`) with automated background sync via `/api/v1/pos/sync` | Automatic idempotent deduplication preventing double-charges |
| **16** | Inventory Synchronization | Shared `products.stock` decremented atomically (`SELECT ... FOR UPDATE`) | 100% synchronized with online e-commerce storefront orders |
| **17** | Keyboard Shortcuts | Standardized shortcut suite (<kbd>F1</kbd> through <kbd>F10</kbd>, <kbd>ESC</kbd>) | All mouse actions have keyboard equivalents |
| **18** | Security & RBAC | Server-side authorization checks (`pos.access`, `pos.sale`, `pos.cash`, `pos.override`, `pos.discount`, `pos.return`) | Reuses existing admin authentication and permission middleware |

---

## 2. Shared Data Integrity

```
+-------------------------------------------------------------------------------+
|                            SHARED MASTER DATA LAYER                           |
+-------------------------------------------------------------------------------+
| Products & Stocks      : products (Shared between Online & POS)              |
| Customers & Wallets    : users (Shared loyalty points, balances, and orders)  |
| Orders & Invoices      : orders + pos_transactions (Unified reporting)        |
| Financial Ledger       : transactions (Consolidated income and expense)       |
| Inventory Move Logs    : inventory_logs (Unified stock traceability)          |
| Promotional Coupons    : coupons (Shared online and in-store promo codes)     |
+-------------------------------------------------------------------------------+
```

## 3. Backward Compatibility Confirmation
- No database tables are dropped or renamed.
- No existing orders or transactions are deleted.
- No existing API endpoints or AJAX routes are broken.
- No changes alter online customer checkout or storefront behavior.
