# GroCo Grocery Store — Caching & Redis Architecture

**Document Version:** 1.0.0  
**Target System:** GroCo Grocery Store  
**Author:** GroCo Architecture & Modernization Engineering  
**Service Class:** `CacheService` (`public/includes/CacheService.php`)

---

## 1. Overview & Strategy

The GroCo caching tier is designed to accelerate read-heavy customer storefront operations (category trees, brand showcases, system settings, promotional banner collections) while strictly preserving database consistency on stock levels and financial orders.

---

## 2. Multi-Tier Cache Hierarchy

The system operates a multi-layer fallback architecture:

```text
Incoming Application Read
         │
         ▼
┌─────────────────────────────────┐
│   Tier 1: Request In-Memory     │  < 0.1ms (Runtime PHP static array)
└─────────────────────────────────┘
         │ (Miss / Uncached)
         ▼
┌─────────────────────────────────┐
│   Tier 2: Redis In-Memory       │  < 1.0ms (TCP / Unix Socket)
└─────────────────────────────────┘
         │ (Fallback if Redis Offline)
         ▼
┌─────────────────────────────────┐
│   Tier 3: File Disk Cache       │  < 5.0ms (storage/cache/*.cache)
└─────────────────────────────────┘
         │ (Miss)
         ▼
┌─────────────────────────────────┐
│   Source of Truth: MySQL PDO    │  Authoritative Data Store
└─────────────────────────────────┘
```

---

## 3. Cached Domains & TTL Policy

| Domain / Key | Tag | TTL | Purpose |
| :--- | :--- | :--- | :--- |
| **System Settings** (`system_settings_all`) | `settings` | 86,400s (24h) | Store name, currency, contact emails, maintenance flags |
| **Category Tree** (`navigation_tree`) | `categories` | 3,600s (1h) | Multi-level hierarchical menu rendering |
| **Active Brands** (`catalog_brands_all`) | `brands` | 3,600s (1h) | Brand filters and homepage carousels |
| **Homepage Banners** (`homepage_banners`) | `banners`, `homepage` | 300s (5m) | Active hero sliders and seasonal deal promos |
| **Flash Deals** (`homepage_flash_sales`) | `products`, `homepage` | 300s (5m) | Flash sale countdown products |

---

## 4. Strict Non-Cached Boundaries (Authoritative MySQL Only)

To prevent phantom stock issues, overselling, and race conditions, the following operations **NEVER read from or rely on cache**:
1. **Real-time Product Stock Levels**: Product checkout verification, POS barcode scans, and inventory transfers always read directly from MySQL with `FOR UPDATE` transaction locks.
2. **Order Placement & Invoicing**: Order insertion, coupon deduction, and payment capture execute inside ACID transactions.
3. **Customer Cart Mutations**: Carts are session or user-bound in MySQL.
4. **Authentication & Password Resets**: Token lookups and brute-force counters remain un-cached in authoritative database records.

---

## 5. Invalidation Triggers

Whenever an administrative change occurs, specific cache tags are purged:

```php
// When a product is created, edited, or stock is manually adjusted:
CacheService::invalidateCatalog();

// When store configuration is updated:
CacheService::invalidateSettings();

// When hero banners are rotated:
CacheService::invalidateBanners();
```
