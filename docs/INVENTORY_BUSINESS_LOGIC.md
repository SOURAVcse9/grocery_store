# GroCo Grocery Store — Inventory & ERP Business Logic Specification

This document details the stock tracking mechanisms, automated inventory deduction, manual adjustments, damage write-offs, purchase order replenishment, valuation formulas, and audit logging across the GroCo platform.

---

## 1. Inventory Movement Types & Lifecycle Triggers

Every physical change in inventory quantity is recorded in the `inventory_logs` audit trail:

```text
┌─────────────────────────┬───────────┬────────────────────────────────────────────────────────┐
│ Movement Type           │ Operator  │ Business Trigger                                       │
├─────────────────────────┼───────────┼────────────────────────────────────────────────────────┤
│ `sale`                  │ - Quantity│ Customer completes online checkout on storefront.      │
│ `pos_sale`              │ - Quantity│ Cashier finalizes sale at retail POS cash register.    │
│ `purchase`              │ + Quantity│ Store manager receives incoming Purchase Order (PO).   │
│ `manual_adjustment`     │ ± Quantity│ Admin performs physical count stock audit correction.  │
│ `order_cancellation`    │ + Quantity│ Pending order is cancelled; items returned to stock.   │
│ `damaged_writeoff`      │ - Quantity│ Perishable/broken items marked damaged and discarded.  │
│ `return_restock`        │ + Quantity│ Delivered customer return inspected and restocked.     │
└─────────────────────────┴───────────┴────────────────────────────────────────────────────────┘
```

---

## 2. Inventory Logs Schema (`inventory_logs`)

* `id` (`INT(11) AUTO_INCREMENT PRIMARY KEY`)
* `product_id` (`INT(11)` &rarr; `products.id`)
* `type` (`VARCHAR(50)` &mdash; `sale`, `pos_sale`, `purchase`, `manual_adjustment`, `order_cancellation`, `damaged_writeoff`, `return_restock`)
* `quantity` (`INT(11)` &mdash; positive or negative movement quantity)
* `stock_before` (`INT(11)` &mdash; inventory snapshot prior to transaction)
* `stock_after` (`INT(11)` &mdash; inventory snapshot immediately following transaction)
* `reference_id` (`INT(11) NULL` &mdash; links to `orders.id`, `purchase_orders.id`, or `stock_adjustments.id`)
* `notes` (`TEXT NULL` &mdash; audit explanation or discrepancy reason)
* `created_by` (`INT(11) NULL` &rarr; `admins.id` &mdash; user or admin responsible)
* `created_at` (`TIMESTAMP DEFAULT CURRENT_TIMESTAMP`)

---

## 3. Stock Level State Rules & Thresholds

For any product with current stock $S$ and minimum alert threshold $M$ (`products.min_stock`, default: 5):

* **In Stock:** $S > M$ (Rendered with green badge, add-to-cart enabled).
* **Low Stock Alert:** $0 < S \le M$ (Rendered with amber badge, displayed in Admin Dashboard low-stock alert table).
* **Out of Stock:** $S \le 0$ (Rendered with red badge, storefront add-to-cart button disabled).

---

## 4. Manual Stock Adjustments (`stock_adjustments`)

* **Table:** `stock_adjustments`
* **Columns:**
  * `id` (`INT(11) AUTO_INCREMENT PRIMARY KEY`)
  * `product_id` (`INT(11)` &rarr; `products.id`)
  * `adjustment_type` (`ENUM('addition', 'subtraction')`)
  * `quantity` (`INT(11)`)
  * `reason` (`VARCHAR(255)` &mdash; e.g. *"Physical audit count reconciliation"*, *"Unrecorded sample giveaway"*)
  * `admin_id` (`INT(11)` &rarr; `admins.id`)
  * `created_at` (`TIMESTAMP`)
* **Execution Logic:**
  * If `addition`: `UPDATE products SET stock = stock + :qty WHERE id = :product_id`.
  * If `subtraction`: `UPDATE products SET stock = stock - :qty WHERE id = :product_id`.
  * Inserts corresponding record into `inventory_logs`.

---

## 5. Damaged & Spoiled Goods Write-Offs (`damaged_products`)

* **Table:** `damaged_products`
* **Columns:**
  * `id` (`INT(11) AUTO_INCREMENT PRIMARY KEY`)
  * `product_id` (`INT(11)` &rarr; `products.id`)
  * `quantity` (`INT(11)`)
  * `loss_amount` (`DECIMAL(10,2)` &mdash; calculated as `quantity * products.cost_price`)
  * `reason` (`TEXT` &mdash; e.g. *"Rotten organic tomatoes"*, *"Broken glass honey bottle"*)
  * `reported_by` (`INT(11)` &rarr; `admins.id`)
  * `created_at` (`TIMESTAMP`)
* **Execution Logic:**
  * Automatically deducts quantity from `products.stock`.
  * Calculates financial loss added to operational accounting expense logs.

---

## 6. Purchase Order Stock Replenishment

* **Tables:** `purchase_orders`, `purchase_order_items`, `suppliers`
* **Replenishment Workflow:**
  1. Store manager creates Purchase Order (PO) to vendor for list of products with unit procurement costs.
  2. When physical goods arrive at warehouse, manager reviews shipment and clicks **"Receive Stock"**.
  3. Inside a database transaction:
     * Updates PO status to `received`.
     * For each line item: `UPDATE products SET stock = stock + :received_qty, cost_price = :unit_cost WHERE id = :product_id`.
     * Inserts log into `inventory_logs (type = 'purchase')`.
     * Updates supplier accounts payable balance in `suppliers`.

---

## 7. Inventory Valuation Formulas

* **1. Total Inventory Valuation at Cost Price (Asset Value):**
  $$\text{Valuation}_{\text{Cost}} = \sum_{p=1}^{N} (\text{products.stock}_p \times \text{products.cost\_price}_p)$$
* **2. Total Inventory Valuation at Retail Price (Potential Revenue):**
  $$\text{Valuation}_{\text{Retail}} = \sum_{p=1}^{N} (\text{products.stock}_p \times \text{products.price}_p)$$
* **3. Potential Gross Profit on Current Inventory:**
  $$\text{Potential Gross Profit} = \text{Valuation}_{\text{Retail}} - \text{Valuation}_{\text{Cost}}$$
