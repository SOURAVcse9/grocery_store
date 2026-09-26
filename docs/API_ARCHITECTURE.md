# GroCo Grocery Store — Modern RESTful API v1 Architecture

**API Version:** 1.0.0  
**Base URL:** `/grocery-store/public/api/v1/`  
**Data Format:** `application/json` (UTF-8)  
**Authentication:** Session Cookies / Bearer API Tokens

---

## 1. Standard Response Envelope

All API endpoints return a standardized, uniform JSON structure:

### Success Response:
```json
{
  "status": "success",
  "code": 200,
  "request_id": "req_84f9b28a91c0e3a47b6a12df",
  "timestamp": "2026-09-26T22:30:00+06:00",
  "data": { ... },
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 142,
    "total_pages": 8
  }
}
```

### Error Response:
```json
{
  "status": "error",
  "code": 400,
  "request_id": "req_84f9b28a91c0e3a47b6a12df",
  "timestamp": "2026-09-26T22:30:00+06:00",
  "data": null,
  "error": {
    "code": "INSUFFICIENT_STOCK",
    "message": "Requested quantity exceeds available inventory."
  }
}
```

---

## 2. Security Controls & Rate Limiting

- **Rate Limiting**: Enforced at 120 requests/minute per client IP using `CacheService` sliding window counters.
- **Strict Headers**: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `X-Request-Id: <unique_id>`.
- **CORS Support**: Configured for cross-origin storefront, PWA, and mobile POS applications with preflight `OPTIONS` resolution.

---

## 3. Endpoints Catalog

| Method | Endpoint | Auth | Purpose |
| :--- | :--- | :--- | :--- |
| `GET` | `/products` | Public | Faceted search, filtering (`category_id`, `brand_id`, `search`, `in_stock`), pagination |
| `GET` | `/products/{id}` | Public | Single product details with responsive image URLs |
| `GET` | `/categories` | Public | Complete active category hierarchy with image URLs |
| `GET` | `/brands` | Public | Active brand directory |
| `GET` | `/search/autocomplete?q=rice` | Public | Fast typeahead query returning top 8 instant product suggestions |
| `GET` | `/cart` | Session | Retrieve active cart line items, subtotal, and 0.00% VAT calculation |
| `POST` | `/cart` | Session | Add or update quantity for item (`product_id`, `quantity`) with stock verification |
| `GET` | `/pos/products?query=...` | Staff | High-speed barcode, SKU, and name query for POS checkout |
| `POST` | `/pos/sale` | Staff | Atomic POS sale processing with `X-Idempotency-Key` duplicate prevention |

---

## 4. OpenAPI 3.0 Specification Snippet

```yaml
openapi: 3.0.3
info:
  title: GroCo Grocery Store REST API
  version: 1.0.0
  description: High-performance, production-hardened API for GroCo e-commerce, PWA, and POS.
paths:
  /api/v1/products:
    get:
      summary: List and filter products
      parameters:
        - name: category_id
          in: query
          schema: { type: integer }
        - name: search
          in: query
          schema: { type: string }
        - name: page
          in: query
          schema: { type: integer, default: 1 }
      responses:
        '200':
          description: Standard paginated product list.
```
