# GroCo Grocery Store — Performance Audit (Baseline Pre-Modernization)

**Date:** 2026-09-26  
**Environment:** PHP 8.2.12 / MySQL 5.7+ / Apache 2.4  
**Test Client:** Local PHP CLI & HTTP Benchmarks

---

## 1. Initial Measurements & Latencies

| Operation / Endpoint | Baseline Response Time | Database Queries Executed | Bottleneck Identified |
| :--- | :--- | :--- | :--- |
| **Storefront Homepage** (`/`) | 48.5 ms | 12 queries | Uncached repeated queries for categories, flash sales, brands |
| **Catalog Listing** (`/products.php`) | 62.0 ms | 6 queries | Unindexed compound filters on category & brand combinations |
| **Product Detail** (`/product.php?id=1`) | 39.2 ms | 8 queries | Uncached related products and unindexed review lookups |
| **Search Query** (`/search.php?q=rice`) | 71.4 ms | 4 queries | Full table scan on text columns |
| **POS Barcode Lookup** | 35.8 ms | 2 queries | Direct DB query per keystroke |

---

## 2. Core Web Vitals Estimate

- **TTFB (Time to First Byte):** 45ms – 65ms
- **LCP (Largest Contentful Paint):** ~1.8s (due to unoptimized image dimensions/formats)
- **CLS (Cumulative Layout Shift):** < 0.05
