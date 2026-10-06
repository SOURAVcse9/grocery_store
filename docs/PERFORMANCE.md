# GroCo Grocery Store — Performance & Optimization Architecture Guide

This document details the performance optimization strategies implemented in **GroCo Grocery Store** to achieve sub-second page loads, optimal Google Core Web Vitals (LCP, CLS, INP), and high database throughput.

---

## 1. Performance Architecture Summary

```
[ Request ] ──► [ Apache Gzip/Brotli Compression ] 
            ──► [ Browser HTTP Caching (Expires: 1 month) ]
            ──► [ PHP 8.2 OPcache In-Memory Bytecode ]
            ──► [ Indexed MySQL 8 Queries (Single Round-Trip Joins) ]
            ──► [ Cloudinary Multi-CDN Edge Image Delivery ]
```

---

## 2. Media & Image Performance (Core Web Vitals)

### Largest Contentful Paint (LCP) Optimization
- **Eager Loading on Hero Assets**: The primary product image on product detail pages and the first 2 above-the-fold catalog items use `loading="eager"` and `fetchpriority="high"`.
- **Below-the-Fold Lazy Loading**: All remaining catalog thumbnails, category cards, and related products utilize native browser `loading="lazy"` and `decoding="async"`.

### Cumulative Layout Shift (CLS) Elimination
- Every `<img>` rendered via `render_optimized_product_image()` or helper functions includes explicit `width` and `height` HTML attributes alongside inline CSS `aspect-ratio: 1/1` (or respective aspect ratio).
- Browsers allocate placeholder space before image byte downloads complete, preventing layout shifts.

### Responsive Breakpoints & Device Density
Using `CloudinaryService::srcset()` or local variant fallback:
```html
<img src="https://res.cloudinary.com/groco/image/upload/w_500,h_500,f_auto,q_auto/groco/products/milk"
     srcset="... 300w, ... 500w, ... 800w, ... 1200w"
     sizes="(max-width: 576px) 50vw, (max-width: 992px) 33vw, 280px"
     alt="Fresh Milk 1L" width="300" height="300" loading="lazy">
```
- Smart mobile devices download ~15KB WebP/AVIF thumbnails instead of a 2MB uncompressed source file.

---

## 3. Database & Query Optimization

### 1. Indexed Lookup Fields
The MySQL schema maintains high-performance B-Tree indexes on all high-frequency query paths:
- `products.slug` (Unique B-Tree) — Sub-millisecond direct lookup by clean URL.
- `products.sku` (B-Tree) — Rapid inventory lookup.
- `products.category_id` & `products.brand_id` — Fast catalog filtering.
- Composite Index `idx_products_cat_active_id (is_active, deleted_at, category_id, id)` — Accelerates active catalog pagination queries.
- Composite Index `idx_products_barcode_active (barcode, is_active, stock)` — Optimizes POS barcode scanner lookups.

### 2. Single-Query Atomic Joins
- Avoids N+1 query patterns by joining categories, brands, and reviews in unified SQL statements with `GROUP BY` or `JSON_ARRAYAGG` where applicable.

### 3. Currency Precision
- All price columns (`price`, `discount_price`, `cost_price`, `tax`, `total`) use fixed-point `DECIMAL(10,2)` rather than floating-point numbers, preventing rounding errors.

---

## 4. HTTP Compression & Browser Caching

Configured in `public/.htaccess`:

### Gzip / Deflate Compression (`mod_deflate`)
Compresses HTTP payloads on the fly:
- HTML, JSON, and XML: ~75% bandwidth reduction.
- CSS and JS: ~70% bandwidth reduction.
- SVG vectors: ~60% bandwidth reduction.

### Browser Cache Expiry (`mod_expires`)
| Asset Category | Expiry Header |
| :--- | :--- |
| **Fonts** (`.woff2`) | `access plus 1 year` |
| **Images** (`.jpg`, `.png`, `.webp`, `.svg`, `.ico`) | `access plus 1 month` |
| **Stylesheets & JavaScript** (`.css`, `.js`) | `access plus 1 week` |

---

## 5. PHP & Server Optimization

### PHP OPcache Configuration
In production `php.ini`, enable OPcache to eliminate PHP script parsing overhead:
```ini
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.validate_timestamps=0
```

### Flash-of-Unstyled-Theme (FOUT) Prevention
Theme dark/light detection runs synchronously in `<head>` before CSS evaluation, ensuring zero layout flickering or re-renders during hydration:
```javascript
(function() {
    const saved = localStorage.getItem('groco-theme') || 'system';
    let theme = saved === 'system' ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : saved;
    document.documentElement.setAttribute('data-theme', theme);
})();
```
