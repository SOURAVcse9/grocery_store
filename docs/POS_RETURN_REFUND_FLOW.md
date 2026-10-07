# GroCo Supermarket POS — Return & Refund Workflow (2026)

## 1. Return Process Lifecycle

```
[Customer arrives with Invoice]
              │
              ▼
1. Invoice Lookup (Return Modal / returnTxnSearch)
   - Search by Order Number (POS-YYYYMMDD-XXXX) or Txn Number (GR-YYYYMMDD-XXXXXX)
   - Fetches original line items, sold quantities, and unit prices
              │
              ▼
2. Itemized Selection
   - Select specific items and returned quantities
   - Verify: Returned Qty <= (Original Qty - Previously Returned Qty)
              │
              ▼
3. Restock & Refund Method
   - Choose Restock to Inventory (True/False)
   - Select Refund Method (Cash from drawer, Card reversal, Wallet credit)
   - Record Reason (e.g. Expired seal, Wrong variant, Customer request)
              │
              ▼
4. Execution & Accounting
   - If Restock = True: products.stock += returned_quantity
   - Ledger Entry: INSERT INTO transactions (type='expense', ...)
   - Audit Log: INSERT INTO pos_audit_logs (event_type='ITEM_RETURNED', ...)
   - Generate Return Slip / Print Confirmation
```
