# GroCo Grocery Store — Point of Sale (POS) Complete Specification

This document provides an exhaustive, step-by-step technical and operational blueprint for the GroCo Retail Point of Sale (POS) cash register system.

---

## 1. POS System Architecture & Workflow

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                          GROCO RETAIL POS TERMINAL                          │
├──────────────────────────────────────┬──────────────────────────────────────┤
│ LEFT PANEL: TRANSACTION REGISTER     │ RIGHT PANEL: PRODUCT DISCOVERY       │
│ - Cashier / Shift Header Status      │ - Fast Search Bar (Name / SKU)       │
│ - Customer Selector / Quick Register │ - Barcode Scanner Direct Listener    │
│ - Dynamic Itemized POS Cart Table    │ - Category Filter Navigation Tabs    │
│ - Quantity Stepper & Price Override  │ - Touch-Friendly Product Cards Grid  │
│ - Subtotal, Discount, Tax, Total     │ - Real-Time Stock Level Badges       │
├──────────────────────────────────────┴──────────────────────────────────────┤
│ FOOTER ACTIONS & MODALS:                                                    │
│ [Hold Order]   [Resume Order]   [Clear Cart]   [Split Payment]   [Checkout] │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Core Operational Modules

### A. Product Discovery & Barcode Scanner Integration
* **Barcode Listener:**
  * Input field with autofocus actively listens for USB / Bluetooth handheld barcode scanner keystrokes (terminated by `Enter` / ASCII 13).
  * System queries: `SELECT * FROM products WHERE (barcode = :code OR sku = :code) AND is_active = 1 AND deleted_at IS NULL LIMIT 1`.
  * **Auto-Add Logic:**
    * If product exists in database and has stock (`stock > 0`):
      * If product is already in POS cart: Increment item quantity by 1.
      * If product is not in POS cart: Append new line item with unit price, name, SKU, and quantity = 1.
      * Play auditory beep feedback.
    * If product is out of stock (`stock <= 0`): Display error notification toast: *"Cannot add item. Product is out of stock."*
* **Live Product Search & Category Tabs:**
  * Instant filter search input queries product name, SKU, or brand.
  * Category tabs allow cashiers to browse items by category (e.g. `Vegetables`, `Beverages`, `Dairy`, `Bakery`, `Meat & Fish`).

---

### B. Customer Selection & Quick Registration
* **Default Customer:** Walk-In / General Customer (`user_id = NULL` or walk-in guest).
* **Customer Lookup:** Fast autocomplete dropdown searching registered customers by Full Name or Phone Number.
* **Quick Add Customer Modal:** Allows cashier to register a new walk-in customer on the fly without leaving the POS screen (Fields: Name, Phone, Email, Address).

---

### C. POS Cart & Calculations Engine
* **Line Item Controls:**
  * Unit Price: Displayed from `products.price` (or `products.discount_price` if on sale).
  * Quantity Stepper: Increment/decrement buttons and direct numeric input.
  * Line Item Discount: Allows cashier to apply an item-specific discount (% or fixed amount).
  * Item Removal: Trash icon removes line item from cart.
* **Calculation Formulas:**
  ```text
  1. Line Item Total    = (Unit Price * Quantity) - Line Item Discount
  2. Cart Subtotal      = Sum of all Line Item Totals
  3. Cart Discount      = Order-level Discount (% or Fixed) + Applied Coupon Value
  4. Taxable Amount     = Cart Subtotal - Cart Discount
  5. VAT / Tax Amount   = Taxable Amount * (Tax Rate % / 100)
  6. Final Payable Total= Taxable Amount + VAT Amount
  ```

---

### D. Hold & Resume Order Subsystem
* **Hold Order Action:**
  * Cashier clicks "Hold Order" (useful when a shopper needs to fetch another grocery item while others are waiting in line).
  * System captures JSON payload of current cart, customer ID, timestamp, and optional reference note, inserting into `pos_hold_orders`.
  * POS screen clears and is immediately ready for the next customer.
* **Resume Order Action:**
  * Cashier clicks "Held Orders" button. Modal displays active held orders with reference number, customer name, items count, and timestamp.
  * Cashier selects an order &rarr; System loads items back into the active POS cart and deletes the held record from `pos_hold_orders`.

---

### E. Payment Processing & Multi-Method Split Payments
* **Payment Modal UI:**
  * Large display of **Final Payable Total (BDT ৳)**.
  * Fast Cash Denomination Buttons (`৳ 100`, `৳ 500`, `৳ 1000`, `৳ 2000`, `Exact Amount`).
  * **Payment Methods:**
    * **Cash:** Cashier enters received amount &rarr; System computes real-time Change Due:
      $$\text{Change Due} = \text{Amount Tendered} - \text{Payable Total}$$
    * **bKash:** Transaction ID input field for mobile wallet confirmation.
    * **Nagad:** Transaction ID input field.
    * **Card (POS Terminal):** Card brand (Visa/Mastercard) + Authorization Code.
    * **Split Payment:** Cashier can allocate split amounts (e.g. `৳ 500` Cash + `৳ 750` bKash).
* **Order Finalization Transaction (`BEGIN TRANSACTION`):**
  1. Validate stock availability for all cart items.
  2. Generate unique POS invoice number: `POS-` + `date('Ymd')` + random 4-digit sequence.
  3. Insert into `orders`:
     * `user_id`: Selected customer ID (or NULL for walk-in).
     * `order_type`: `'pos'`
     * `status`: `'delivered'`
     * `payment_status`: `'paid'`
     * `payment_method`: Selected method (`cash`, `bkash`, `card`, `split`).
     * `subtotal`, `discount_amount`, `tax_amount`, `total_amount`.
     * `created_at`: `NOW()`
  4. Insert into `order_items` for each product.
  5. Deduct stock: `UPDATE products SET stock = stock - :qty WHERE id = :product_id`.
  6. Insert into `inventory_logs`: `(product_id, type='pos_sale', quantity=:qty, reference_id=:order_id)`.
  7. Insert into `transactions`: Records financial receipt in store accounting.
  8. If POS shift is active: Append cash sales to active shift in `pos_shifts`.
  9. `COMMIT TRANSACTION`.
  10. Trigger auto-receipt popup and clear POS cart.

---

### F. Thermal Receipt Printing (`admin/pos/receipt.php`)
* **Print Layout:** Formatted specifically for standard 80mm and 58mm thermal point-of-sale receipt printers.
* **Receipt Content:**
  * Store Header: Store Logo, Store Name ("GroCo Grocery Store"), Address, Phone, Email, VAT Registration Number.
  * Order Metadata: Invoice Number, Date & Time, Cashier Name, Customer Name & Phone.
  * Tabular Item List: Item Name, Unit Price, Qty, Total Amount.
  * Summary Financials: Subtotal, Discount, VAT (0%), Total Payable, Amount Tendered, Change Due, Payment Method.
  * Footer: Barcode of Invoice Number for fast returns/scans, "Thank you for shopping with GroCo!", Return policy terms.
* **Browser Print Trigger:** Automatically executes `window.print()` upon opening.

---

### G. Cashier Shift Management & Drawer Reconciliation
* **Open Shift:** Cashier enters opening float cash balance in drawer upon logging in (`pos_shifts` / `cashier_shifts`).
* **Close Shift:** Cashier enters counted closing cash balance. System generates shift summary report:
  * Opening Float
  * + Total Cash Sales
  * + Total Digital Payments (bKash/Nagad/Card)
  * - Cash Refunds / Paid-outs
  * = Expected Cash Balance vs Actual Counted Cash
  * Discrepancy (Cash Over / Cash Shortage) calculation.
