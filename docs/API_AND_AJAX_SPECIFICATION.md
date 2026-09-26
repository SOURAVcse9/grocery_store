# API and AJAX Specification

## 1. Overview & Architecture

The GroCo Grocery Store application uses an AJAX/REST-like hybrid API layer primarily situated under `/public/api/` for client storefront operations and `/admin/api/` for backoffice analytics. In addition, several controller scripts in the admin and public directories accept asynchronous POST and GET requests.

### Core Architecture Standards:
- **Transport**: JSON payloads and `application/x-www-form-urlencoded` / `multipart/form-data`.
- **Response Format**: Uniform JSON responses:
  ```json
  {
    "success": true|false,
    "message": "Human readable message",
    "data": { ... },
    "error": "Error details (if failure)"
  }
  ```
- **Session & CSRF**:
  - State is maintained via PHP sessions (`PHPSESSID`).
  - Mutating operations (POST/PUT/DELETE) require valid CSRF verification via `$_SESSION['csrf_token']` (passed in header `X-CSRF-TOKEN` or POST parameter `csrf_token`).
- **Rate Limiting**:
  - Auth and write endpoints are throttle-guarded via `rate_limit.php` by IP and session key.

---

## 2. Public Storefront AJAX Endpoints (`/public/api/`)

### 2.1 Cart Endpoint (`/public/api/cart.php`)
Handles real-time cart mutations, line-item adjustments, and mini-cart sync.

| Action / Method | Parameters | Business Logic & Validations | Response Structure |
| :--- | :--- | :--- | :--- |
| `POST ?action=add` | `product_id` (int, required)<br>`quantity` (int, optional, default 1)<br>`variant_id` (int, optional) | Validates product exists, `is_active = 1`, and requested stock $\le$ available stock. If logged in, upserts to `cart_items` table; otherwise modifies `$_SESSION['cart']`. | `{"success": true, "cart_count": 5, "cart_total": "1250.00", "item": {...}}` |
| `POST ?action=update` | `product_id` (int)<br>`quantity` (int, min 1, max max_order_qty) | Updates line quantity, recalculates item subtotal and cart aggregate. Checks stock limit. | `{"success": true, "item_total": "350.00", "subtotal": "1250.00", "tax": "0.00", "shipping": "60.00", "grand_total": "1310.00"}` |
| `POST ?action=remove` | `product_id` (int)<br>`variant_id` (int, optional) | Deletes specified line item from database or session. | `{"success": true, "cart_count": 4, "cart_total": "900.00"}` |
| `POST ?action=clear` | None (CSRF required) | Clears all cart items for active session/user. | `{"success": true, "cart_count": 0, "cart_total": "0.00"}` |
| `GET ?action=get` | None | Retrieves all items in current cart, resolved with live pricing, discount tags, and stock flags. | `{"success": true, "items": [...], "summary": {"item_count": 4, "subtotal": 900.00, "formatted_total": "৳ 900.00"}}` |
| `POST ?action=apply_coupon` | `coupon_code` (string, required) | Verifies coupon existence, validity window, usage limits, and minimum spend. Calculates discount value. | `{"success": true, "discount": "100.00", "discount_type": "fixed", "new_total": "800.00", "message": "Coupon applied!"}` |
| `POST ?action=remove_coupon`| None | Clears applied coupon from session. | `{"success": true, "message": "Coupon removed"}` |

---

### 2.2 Product Live Search & Filtering (`/public/api/search.php` & `/public/api/products.php`)

| Endpoint | Method | Parameters | Logic & Behavior |
| :--- | :--- | :--- | :--- |
| `/public/api/search.php` | `GET` | `q` (string, min 2 chars)<br>`category` (int, optional)<br>`limit` (int, default 10) | Performs full-text `LIKE %q%` search on `products.name`, `products.sku`, `products.description`, matching active categories. Returns thumbnail, pricing, and stock status for autocomplete. |
| `/public/api/products.php` | `GET` | `page` (int)<br>`category_id` (int)<br>`brand_id` (int)<br>`min_price` (float)<br>`max_price` (float)<br>`sort` (enum: `price_asc`, `price_desc`, `newest`, `popular`, `rating`)<br>`in_stock` (bool) | Filter engine returning paginated product cards with active promotional pricing, average review ratings, and badge calculations (`sale`, `out_of_stock`, `hot`). |
| `/public/api/products.php?action=quickview` | `GET` | `product_id` (int, required) | Fetches complete product modal payload: gallery images, short description, SKU, unit measurement, stock level, brand info, and available variants. |

---

### 2.3 Wishlist API (`/public/api/wishlist.php`)

| Method & Action | Parameters | Auth Required | Description & DB Operations |
| :--- | :--- | :--- | :--- |
| `POST ?action=toggle` | `product_id` (int) | Yes (Customer) | If item in `wishlist` for `user_id`, delete row; else insert. Returns updated toggle state. |
| `GET ?action=list` | None | Yes (Customer) | Returns array of wishlisted product IDs and hydrated product objects. |
| `POST ?action=move_to_cart`| `product_id` (int) | Yes (Customer) | Removes item from `wishlist` and inserts into `cart_items`. |

---

### 2.4 Product Compare API (`/public/api/compare.php`)

| Method & Action | Parameters | Storage | Description |
| :--- | :--- | :--- | :--- |
| `POST ?action=add` | `product_id` (int) | Session (`$_SESSION['compare']`, max 4 items) | Adds product to compare drawer. Enforces category consistency or 4-item limit. |
| `POST ?action=remove` | `product_id` (int) | Session | Removes specific item from comparison tray. |
| `GET ?action=get` | None | Session | Returns comparison schema (attributes, unit, price, brand, rating, stock) for all compared products. |

---

### 2.5 Reviews & Ratings API (`/public/api/reviews.php`)

| Method & Action | Parameters | Auth Required | Description & Verification |
| :--- | :--- | :--- | :--- |
| `GET ?action=get` | `product_id` (int)<br>`page` (int) | No | Fetches approved reviews (`status = 'approved'`) with star rating, customer name, date, and review body. |
| `POST ?action=submit` | `product_id` (int)<br>`rating` (int, 1-5)<br>`comment` (string)<br>`title` (string) | Yes (Customer) | Submits review. Checks setting `reviews_moderation_enabled`; if true, sets `status = 'pending'`, otherwise `'approved'`. Validates verified purchase flag against `orders` table. |

---

### 2.6 Customer Notifications API (`/public/api/notifications.php`)

| Method & Action | Parameters | Auth Required | Description |
| :--- | :--- | :--- | :--- |
| `GET ?action=unread_count`| None | Yes (Customer) | Returns integer count of unread customer alerts in `notifications`. |
| `GET ?action=list` | `limit` (int, default 10) | Yes (Customer) | Returns customer alerts (order updates, promo alerts, stock back notices). |
| `POST ?action=mark_read` | `id` (int, optional - all if null) | Yes (Customer) | Sets `is_read = 1` for notification records belonging to `$_SESSION['user_id']`. |

---

### 2.7 Analytics Tracking API (`/public/api/analytics.php`)

| Method | Payload | Purpose |
| :--- | :--- | :--- |
| `POST` | `{"event": "page_view"|"add_to_cart"|"view_product", "data": {...}}` | Privacy-friendly internal telemetry logging to `analytics_events` for store conversion rate tracking. |

---

## 3. Admin Backoffice Endpoints (`/admin/api/` & Asynchronous Controllers)

### 3.1 Dashboard Analytics (`/admin/api/dashboard_charts.php`)
- **Method**: `GET`
- **Auth**: Admin Session with `dashboard.view` permission.
- **Parameters**: `range` (enum: `7days`, `30days`, `this_month`, `this_year`, `custom`), `start_date`, `end_date`.
- **Payload Response**:
  ```json
  {
    "success": true,
    "sales_chart": {
      "labels": ["2026-09-20", "2026-09-21", "2026-09-22", "2026-09-23", "2026-09-24", "2026-09-25", "2026-09-26"],
      "revenue": [15200.00, 22400.00, 18900.00, 31200.00, 27800.00, 34500.00, 29000.00],
      "orders_count": [12, 18, 14, 25, 20, 28, 22]
    },
    "order_status_distribution": {
      "pending": 8,
      "processing": 14,
      "shipped": 22,
      "delivered": 145,
      "cancelled": 3
    },
    "top_selling_products": [
      {"name": "Miniket Rice 5kg", "units_sold": 84, "revenue": 37800.00},
      {"name": "Soybean Oil 5L", "units_sold": 62, "revenue": 52700.00}
    ]
  }
  ```

---

### 3.2 POS Real-time Endpoints (`/admin/pos/`)

| Action / Script | Method | Parameters | Behavior |
| :--- | :--- | :--- | :--- |
| `admin/pos/search_product.php` | `GET` | `query` (barcode, SKU, or name) | Returns matching product with current price, stock, and VAT calculation for instant cashier scanning. |
| `admin/pos/search_customer.php` | `GET` | `query` (phone or name) | Autocompletes customer records (`users` table) with reward points balance and credit status. |
| `admin/pos/process_sale.php` | `POST` | `customer_id`, `items[]`, `discount_type`, `discount_val`, `payment_method`, `cash_received`, `shift_id` | Atomic transaction: inserts `orders` (`order_type = 'pos'`), inserts `order_items`, updates `products.stock`, logs `inventory_logs`, creates `finance_transactions` entry, returns invoice JSON for ESC/POS printing. |
| `admin/pos/hold_order.php` | `POST` | `cart_data`, `customer_id`, `note` | Persists suspended cart into `pos_held_orders` table. |
| `admin/pos/get_held_orders.php` | `GET` | None | Returns list of currently suspended orders for cashier station. |

---

## 4. Error Handling & HTTP Status Standards

| Status Code | Meaning | Example Scenario |
| :--- | :--- | :--- |
| `200 OK` | Request succeeded | Successful cart update, search results returned. |
| `400 Bad Request` | Invalid input payload | Missing `product_id`, negative quantity, malformed JSON. |
| `401 Unauthorized` | Not authenticated | Attempting to access wishlist or submit review without user session. |
| `403 Forbidden` | Permission denied / CSRF fail | Invalid CSRF token, or cashier attempting admin settings change. |
| `404 Not Found` | Resource missing | Product ID does not exist in database. |
| `422 Unprocessable` | Business validation failed | Out of stock, coupon expired, minimum spend not reached. |
| `429 Too Many Requests`| Rate limit exceeded | Exceeded 60 search requests per minute or 5 failed login attempts. |
| `500 Server Error` | Database/Fatal PHP error | Unhandled exception, DB transaction failure (returns sanitized JSON). |
