# GroCo Grocery Store — Point-of-Sale (POS) Modernization Architecture

**Document Version:** 1.0.0  
**Target Module:** `admin/pos/`  
**API Endpoints:** `GET /api/v1/pos/products`, `POST /api/v1/pos/sale`  
**Author:** GroCo POS & Retail Modernization Engineering

---

## 1. Overview & Objectives

The modernized POS terminal combines rapid in-store barcode scanning and split-tender payment processing with resilient offline queuing and strict server-authoritative inventory reconciliation.

---

## 2. Architecture & Idempotent Transaction Lifecycle

```text
Cashier Terminal (Browser / Tablet)
         │
         ├──► 1. Barcode / SKU Hardware Scan (Debounced lookup via /api/v1/pos/products)
         │
         ├──► 2. Local Cart Stored in localStorage / IndexedDB
         │
         ├──► 3. Tender Checkout: Generates client_tx_id (UUIDv4)
         │
         ├──► 4. POST /api/v1/pos/sale (with Header X-Idempotency-Key: client_tx_id)
         │
         ▼
Server Order Processor
         │
         ├──► Check orders.notes for IDEMPOTENCY:client_tx_id
         │      └── [Found Duplicate] ──► Return 200 OK with existing Order Details (Zero Duplicate Charge)
         │
         ├──► [New Transaction] ──► Begin ACID MySQL Transaction
         │      ├── Lock product rows with SELECT ... FOR UPDATE
         │      ├── Verify stock_quantity >= requested quantity
         │      ├── Deduct stock (UPDATE products SET stock_quantity = stock_quantity - ?)
         │      ├── Insert orders and order_items records
         │      └── Commit Transaction
         │
         ▼
Thermal Receipt Generation (Formatted for 80mm / 58mm thermal receipt printers)
```

---

## 3. Key Modernized Capabilities

1. **Hardware Barcode Integration**: Supports standard USB & Bluetooth 1D/2D HID barcode scanners with instant autofocus on POS search fields.
2. **Offline-Safe Transaction Queue**: If Wi-Fi briefly drops during in-store checkout, transactions are stored in an encrypted client queue and synced automatically once internet is restored.
3. **Zero Order Duplication**: Every transaction requires an `X-Idempotency-Key`. Retries or repeated clicks cannot create duplicate order rows or double-deduct inventory.
4. **Thermal Printer Compatibility**: Optimized CSS print styles for 80mm and 58mm thermal rolls with auto-cut commands and printable QR verification codes.
