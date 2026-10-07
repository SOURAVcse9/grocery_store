# GroCo Grocery Store — REST API v1 Specification

**API Version**: `1.0.0`  
**Base URL**: `/grocery-store/public/api/v1`  
**Response Format**: `application/json; charset=UTF-8`

---

## 1. Authentication & Security Headers

| Header | Type | Description |
| :--- | :--- | :--- |
| `Content-Type` | `string` | Must be `application/json` for POST/PUT requests |
| `X-Request-Id` | `string` | Unique request tracking identifier generated per transaction |
| `X-Idempotency-Key` | `string` | Required for POS checkout transactions (`/pos/sale`) |

---

## 2. API Endpoints Reference

### A. Catalog & Search Endpoints

#### `GET /products`
Retrieve paginated list of active products with faceted filtering.
- **Parameters**:
  - `page` (int, default: 1)
  - `per_page` (int, default: 20, max: 50)
  - `category_id` (int, optional)
  - `brand_id` (int, optional)
  - `search` (string, optional)
  - `in_stock` (int, 0 or 1)
- **Response**:
  ```json
  {
    "status": "success",
    "data": [
      {
        "id": 1,
        "name": "Organic Fresh Milk",
        "slug": "organic-fresh-milk",
        "price": 4.50,
        "discount_price": 3.99,
        "stock": 45,
        "image_url": "http://localhost:8080/grocery-store/public/uploads/products/milk.webp"
      }
    ],
    "meta": { "page": 1, "per_page": 20, "total": 1, "total_pages": 1 }
  }
  ```

#### `GET /products/{id}`
Retrieve single product details including responsive image map.

#### `GET /categories`
Retrieve active categories hierarchy.

#### `GET /brands`
Retrieve active brand entities with logos.

#### `GET /search/autocomplete?q={term}`
Instant debounced autocomplete returning matching products and categories.

---

### B. Shopping Cart & Checkout Endpoints

#### `GET /cart`
Retrieve customer / session shopping cart with line items and Zero-VAT calculations.

#### `POST /cart`
Add or update quantity for an item.
- **Body**: `{"product_id": 1, "quantity": 2}`

#### `POST /coupons/validate`
Validate coupon code against active cart total.
- **Body**: `{"code": "GROCO10", "subtotal": 50.00}`

#### `POST /orders`
Create online customer order with server-authoritative stock deduction and transaction safety.
- **Body**:
  ```json
  {
    "items": [{"product_id": 1, "quantity": 2}],
    "address_id": 5,
    "payment_method": "cod",
    "note": "Ring front door bell"
  }
  ```

---

### C. POS (Point of Sale) Endpoints (Staff Guarded)

#### `GET /pos/products?query={barcode_or_sku}`
Fast POS barcode lookup with stock status.

#### `POST /pos/sale`
Atomic in-store sale completion with `X-Idempotency-Key` replay protection.

---

### D. Customer Authentication Endpoints

#### `POST /auth/login`
Authenticate customer credentials and establish session.

#### `POST /auth/register`
Register new customer account.

#### `POST /auth/logout`
Terminate customer session.

#### `GET /auth/me`
Retrieve authenticated customer profile.
