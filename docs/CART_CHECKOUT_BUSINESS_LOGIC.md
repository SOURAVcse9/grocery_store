# GroCo Grocery Store — Cart & Checkout Business Logic Specification

This document details the exact mathematical formulas, data structures, session handling, validation rules, coupon mechanisms, delivery tier logic, and checkout transaction workflows.

---

## 1. Cart Data Structures & Storage Strategy

GroCo uses a hybrid database-backed cart architecture supporting both anonymous guest shoppers and logged-in customers:

```text
┌──────────────────────────────────────┐       ┌──────────────────────────────────────┐
│        ANONYMOUS GUEST VISITOR       │       │        AUTHENTICATED CUSTOMER        │
├──────────────────────────────────────┤       ├──────────────────────────────────────┤
│ Token: `$_SESSION['guest_token']`    │       │ Identifier: `$_SESSION['customer_id']`│
│ Carts Table: `session_id = :token`   │       │ Carts Table: `user_id = :customer_id`│
│ Cart Items: Linked via `cart_id`     │       │ Cart Items: Linked via `cart_id`     │
└──────────────────────────────────────┘       └──────────────────────────────────────┘
                                  ▲                │
                                  │   ON LOGIN     │
                                  └────────────────┘
                          (Guest cart automatically merged)
```

### Database Tables:
* **`carts` Table:**
  * `id` (`INT(11) AUTO_INCREMENT`)
  * `user_id` (`INT(11) NULL` &rarr; `users.id`)
  * `session_id` (`VARCHAR(255) NULL` &mdash; 40-character hex guest token)
  * `created_at`, `updated_at`
* **`cart_items` Table:**
  * `id` (`INT(11) AUTO_INCREMENT`)
  * `cart_id` (`INT(11)` &rarr; `carts.id`)
  * `product_id` (`INT(11)` &rarr; `products.id`)
  * `quantity` (`INT(11) DEFAULT 1`)
  * `price` (`DECIMAL(10,2)` &mdash; unit price snapshot at time of addition)
  * `created_at`, `updated_at`

---

## 2. Exact Financial Calculation Formulas

All monetary calculations are performed in **Bangladeshi Taka (BDT ৳)** using 2 decimal places:

### A. Line Item Calculation
For each item $i$ in the cart:
$$\text{Line Price}_i = \begin{cases} \text{discount\_price}_i & \text{if } \text{discount\_price}_i > 0 \text{ and } \text{discount\_price}_i < \text{price}_i \\ \text{price}_i & \text{otherwise} \end{cases}$$
$$\text{Line Item Total}_i = \text{Line Price}_i \times \text{Quantity}_i$$

### B. Cart Subtotal
$$\text{Subtotal} = \sum_{i=1}^{n} \text{Line Item Total}_i$$

### C. Coupon Discount Calculation
If a valid coupon $C$ is applied:
* If $C.\text{type} = \text{'percentage'}$:
  $$\text{Coupon Discount} = \text{round}\left( \text{Subtotal} \times \frac{C.\text{discount\_percent}}{100}, 2 \right)$$
  *(Subject to maximum discount cap if configured in coupon record)*
* If $C.\text{type} = \text{'fixed\_amount'}$:
  $$\text{Coupon Discount} = \min(C.\text{discount\_amount}, \text{Subtotal})$$
* If no coupon is applied: $\text{Coupon Discount} = 0.00$.

### D. Delivery / Shipping Fee Calculation
* Base Shipping Charge is configured in `settings.site_shipping_charge` (Default: `৳ 60.00`).
* Free Delivery Threshold is configured in `settings.site_min_order` (Default: `৳ 1000.00` or per promotion).
$$\text{Delivery Fee} = \begin{cases} 0.00 & \text{if } \text{Subtotal} \ge \text{Free Delivery Threshold} \\ \text{site\_shipping\_charge} & \text{otherwise} \end{cases}$$

### E. Tax / VAT Calculation
* Configured in `settings.site_tax` (Default: `0.00%` for essential fresh groceries in Bangladesh, or applicable VAT rate).
$$\text{Net Taxable Amount} = \max(0, \text{Subtotal} - \text{Coupon Discount})$$
$$\text{VAT Amount} = \text{round}\left( \text{Net Taxable Amount} \times \frac{\text{site\_tax}}{100}, 2 \right)$$

### F. Grand Total Formula
$$\text{Grand Total} = \text{Net Taxable Amount} + \text{Delivery Fee} + \text{VAT Amount}$$

---

## 3. Stock & Availability Validation Rules

* **Add to Cart:**
  * System queries: `SELECT stock FROM products WHERE id = :id AND is_active = 1`.
  * If requested quantity $>$ `products.stock`: Halts with error: *"Cannot add requested quantity. Only {stock} units available."*
* **Checkout Lock:**
  * Prior to finalizing order placement in `public/process_checkout.php`, system re-queries live stock levels for all items inside a database transaction (`SELECT ... FOR UPDATE` / transaction isolation).
  * If any item went out of stock during the checkout session, transaction rolls back with descriptive item error.

---

## 4. Coupon Validation Engine (`public/ajax/coupon.php`)

* **Verification Checks:**
  1. Check code existence: `SELECT * FROM coupons WHERE code = :code AND is_active = 1 LIMIT 1`.
  2. Check date validity: `valid_from <= NOW() AND (valid_until IS NULL OR valid_until >= NOW())`.
  3. Check minimum spend: `Subtotal >= coupons.min_purchase`.
  4. Check overall usage limit: `usage_count < coupons.usage_limit`.
  5. Check customer usage limit: If logged in, query `orders` count where `coupon_code = :code AND user_id = :user_id`. If $\ge$ `coupons.per_user_limit`, reject.
* **Success Result:** Returns JSON `{ success: true, discount_amount: ..., new_total: ... }` and stores `$_SESSION['applied_coupon'] = $couponData`.

---

## 5. Checkout Process & Order Creation Transaction

* **Route:** `public/process_checkout.php`
* **HTTP Method:** `POST` with CSRF token verification.
* **Atomic Execution Steps:**
  ```text
  1. START DATABASE TRANSACTION (BEGIN)
  2. Validate session cart items count > 0.
  3. Validate delivery address (Name, Phone +8801X..., Address, City, Area).
  4. Validate delivery time slot.
  5. Re-verify live stock availability for all cart items.
  6. Compute final Subtotal, Discount, Delivery Fee, VAT, and Grand Total.
  7. Generate unique invoice code: INV-{Ymd}-{rand4}.
  8. Insert Master Order record into `orders`:
     - user_id, order_number, invoice_number, total_amount, subtotal,
     - discount_amount, delivery_charge, vat_amount, payment_method,
     - payment_status ('unpaid' for COD / 'paid' for instant gateway),
     - status ('pending'), delivery_address, delivery_slot, created_at.
  9. For each item in cart, insert into `order_items`:
     - order_id, product_id, product_name, sku, unit_price, quantity, total_price.
  10. Deduct inventory: UPDATE products SET stock = stock - quantity WHERE id = product_id.
  11. Insert audit log into `inventory_logs`: type = 'sale', reference_id = order_id.
  12. If coupon used: UPDATE coupons SET usage_count = usage_count + 1 WHERE id = coupon_id.
  13. Insert initial status history: INSERT INTO order_status_history (order_id, status, notes).
  14. Clear customer cart: DELETE FROM cart_items WHERE cart_id = :cart_id.
  15. COMMIT DATABASE TRANSACTION.
  ```

---

## 6. Payment Method Gateways & Handlers

* **1. Cash on Delivery (COD):**
  * `payment_method = 'cod'`, `payment_status = 'unpaid'`.
  * Order is immediately placed; customer redirected to `public/thank-you.php?order_id={id}`.
* **2. SSLCommerz Multi-Channel Payment Gateway:**
  * Initializes payment session via SSLCommerz API (Debit/Credit Cards, Mobile Banking, Internet Banking).
  * Callback endpoints:
    * Success: `public/payment-callback.php?status=success` &rarr; updates `orders.payment_status = 'paid'`, records `transactions`.
    * Fail: `public/payment-callback.php?status=fail` &rarr; marks payment failed, retains order for retry.
    * Cancel: `public/payment-callback.php?status=cancel` &rarr; returns customer to checkout.
* **3. bKash / Nagad Direct Mobile Banking:**
  * Supports manual transaction ID submission or direct checkout redirect.
