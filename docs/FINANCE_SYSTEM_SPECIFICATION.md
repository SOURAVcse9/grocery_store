# GroCo Grocery Store — Finance & Accounting Specification

This document details the financial architecture, revenue calculation, operational expense tracking, profit & loss formulas, daily drawer closing reconciliation, and transactional accounting structures.

---

## 1. Financial Data Model Overview

```text
┌──────────────────────────────────────┐       ┌──────────────────────────────────────┐
│           REVENUE STREAMS            │       │          EXPENSE & LOSS STREAMS      │
├──────────────────────────────────────┤       ├──────────────────────────────────────┤
│ - Storefront Delivered Orders        │       │ - Cost of Goods Sold (COGS)          │
│ - Retail POS Terminal Sales          │       │ - Operational Expenses (`expenses`)  │
│ - Delivery Fee Collections           │       │ - Damaged / Spoiled Goods Losses     │
└──────────────────────────────────────┘       └──────────────────────────────────────┘
                                  ▼                ▼
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                             NET PROFIT / LOSS ENGINE                                │
│          Net Profit = Gross Sales Revenue - COGS - Total Operational Expenses        │
└─────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Core Finance Tables & Schemas

### A. Operational Expenses (`expenses`)
* `id` (`INT(11) AUTO_INCREMENT PRIMARY KEY`)
* `title` (`VARCHAR(255)` &mdash; Expense title / description)
* `category_id` (`INT(11)` &rarr; `expense_categories.id`)
* `amount` (`DECIMAL(10,2)` &mdash; Expense amount in BDT)
* `expense_date` (`DATE` &mdash; Date incurred)
* `payment_method` (`VARCHAR(50)` &mdash; `cash`, `bank_transfer`, `bkash`, `card`)
* `reference_number` (`VARCHAR(100) NULL` &mdash; Receipt / Voucher / Bill Number)
* `attachment` (`VARCHAR(255) NULL` &mdash; Scanned receipt image in `public/uploads/expenses/`)
* `notes` (`TEXT NULL`)
* `created_by` (`INT(11)` &rarr; `admins.id`)
* `created_at`, `updated_at`

### B. Expense Categories (`expense_categories`)
* `id` (`INT(11) AUTO_INCREMENT PRIMARY KEY`)
* `name` (`VARCHAR(100)` &mdash; e.g. `Shop Rent`, `Electricity & Utilities`, `Employee Salaries`, `Packaging Materials`, `Transportation & Fuel`, `Office Maintenance`, `Marketing & Ads`)
* `description` (`TEXT NULL`)
* `is_active` (`TINYINT(1) DEFAULT 1`)

### C. Financial Transactions Ledger (`transactions`)
* `id` (`INT(11) AUTO_INCREMENT PRIMARY KEY`)
* `order_id` (`INT(11) NULL` &rarr; `orders.id`)
* `user_id` (`INT(11) NULL` &rarr; `users.id`)
* `transaction_type` (`ENUM('credit', 'debit')` &mdash; Credit = Income, Debit = Expense/Refund)
* `amount` (`DECIMAL(10,2)`)
* `payment_method` (`VARCHAR(50)` &mdash; `cash`, `bkash`, `nagad`, `sslcommerz`, `card`)
* `transaction_reference` (`VARCHAR(100) UNIQUE` &mdash; Gateway Trx ID / Voucher)
* `status` (`ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'completed'`)
* `created_at` (`TIMESTAMP DEFAULT CURRENT_TIMESTAMP`)

### D. Daily Cash Closing & Drawer Reconciliation (`daily_closings`)
* `id` (`INT(11) AUTO_INCREMENT PRIMARY KEY`)
* `closing_date` (`DATE UNIQUE`)
* `opening_balance` (`DECIMAL(10,2)` &mdash; Float cash at start of day)
* `total_cash_sales` (`DECIMAL(10,2)` &mdash; Sum of cash orders and POS cash sales)
* `total_digital_sales` (`DECIMAL(10,2)` &mdash; Sum of bKash, Nagad, Card transactions)
* `total_expenses` (`DECIMAL(10,2)` &mdash; Petty cash paid out during the day)
* `total_refunds` (`DECIMAL(10,2)` &mdash; Cash refunded for returns)
* `expected_cash` (`DECIMAL(10,2)` &mdash; `opening_balance + total_cash_sales - total_expenses - total_refunds`)
* `actual_cash` (`DECIMAL(10,2)` &mdash; Physical cash counted in register)
* `discrepancy` (`DECIMAL(10,2)` &mdash; `actual_cash - expected_cash`: Positive = Cash Over, Negative = Cash Shortage)
* `notes` (`TEXT NULL`)
* `closed_by` (`INT(11)` &rarr; `admins.id`)
* `created_at` (`TIMESTAMP DEFAULT CURRENT_TIMESTAMP`)

---

## 3. Financial Calculation Formulas

### A. Gross Sales Revenue (Period $T$)
$$\text{Gross Revenue} = \sum_{\substack{\text{orders} \in T \\ \text{status} = \text{'delivered'}}} \text{orders.total\_amount}$$

### B. Cost of Goods Sold (COGS)
For every product $p$ sold in delivered order items:
$$\text{COGS} = \sum_{\substack{\text{order\_items} \in T \\ \text{orders.status} = \text{'delivered'}}} (\text{order\_items.quantity} \times \text{products.cost\_price})$$

### C. Gross Profit
$$\text{Gross Profit} = \text{Gross Revenue} - \text{COGS}$$
$$\text{Gross Profit Margin \%} = \left( \frac{\text{Gross Profit}}{\text{Gross Revenue}} \right) \times 100$$

### D. Total Operational Expenses
$$\text{Total Expenses} = \sum_{\text{expenses} \in T} \text{expenses.amount} + \sum_{\text{damaged} \in T} \text{damaged\_products.loss\_amount}$$

### E. Net Profit
$$\text{Net Profit} = \text{Gross Profit} - \text{Total Expenses}$$
$$\text{Net Profit Margin \%} = \left( \frac{\text{Net Profit}}{\text{Gross Revenue}} \right) \times 100$$

---

## 4. Reports & Financial Statements

* **Sales Summary Report (`admin/reports/sales.php`):**
  * Filter by Custom Date Range (Today, Yesterday, Last 7 Days, This Month, Custom).
  * Group by Day / Week / Month.
  * Tabular Breakdown: Date, Orders Count, Items Sold, Gross Sales, Discounts Given, Delivery Fees, Net Total.
* **Profit & Loss Statement (`admin/reports/profit-loss.php`):**
  * Displays itemized P&L:
    * (+) Sales Revenue
    * (-) Cost of Goods Sold
    * (=) Gross Profit
    * (-) Operating Expenses by Category
    * (-) Damaged Product Losses
    * (=) Net Income / Net Profit
* **Payment Method Distribution:**
  * Chart and table showing percentage share of Cash on Delivery vs bKash vs Nagad vs Credit/Debit Cards.
* **CSV / Excel Export Engine:**
  * Generates formatted spreadsheet reports with headers and sum formulas.
