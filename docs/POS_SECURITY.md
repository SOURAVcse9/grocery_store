# GroCo Supermarket POS — Security Architecture & RBAC (2026)

## 1. Role-Based Access Control (RBAC) Permissions

| Permission Name | Assigned Roles | Description |
| :--- | :--- | :--- |
| `pos.access` | Cashier, Supervisor, Admin | View and navigate to POS Counter Terminal |
| `pos.sale` | Cashier, Supervisor, Admin | Execute sales transactions, hold & resume carts |
| `pos.cash` | Cashier, Supervisor, Admin | Open shift drawer, record petty movements, close shift |
| `pos.override` | Store Manager, Supervisor, Admin | Authorize price overrides and markdowns |
| `pos.discount` | Store Manager, Supervisor, Admin | Authorize discounts exceeding standard limit (15%) |
| `pos.return` | Store Manager, Supervisor, Admin | Process itemized returns, restock and refund payouts |

## 2. Security Controls & Guardrails

1. **CSRF Token Protection**: Every state-changing AJAX POST request requires a valid CSRF token header/field.
2. **Session Hijacking Prevention**: Cashier sessions are strictly bound to authenticated admin credentials and IP addresses.
3. **Immutable Audit Logging**: All sensitive POS events (`SHIFT_OPENED`, `SALE_CREATED`, `PRICE_OVERRIDDEN`, `DRAWER_TX`, `ITEM_RETURNED`, `RECEIPT_REPRINTED`) are written immediately to `pos_audit_logs`.
4. **Data Sanitization**: Card numbers, CVVs, and passwords are automatically redacted before persisting into metadata JSON blobs.
