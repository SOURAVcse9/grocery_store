# GroCo Grocery Store — Modern PWA Architecture (v2.0.0)

**Document Version:** 2.0.0  
**Target System:** GroCo Grocery Store Storefront  
**Manifest:** `public/manifest.json`  
**Service Worker:** `public/sw.js`

---

## 1. Service Worker Cache Strategies

The GroCo PWA employs a multi-tiered caching strategy designed to deliver near-instant load times while guaranteeing zero stale or insecure data:

```text
Incoming Web Request
         │
         ├─── [Method !== 'GET' OR Sensitive Route (admin, auth, checkout, POS)] ──► Direct Network (No Cache)
         │
         ├─── [Static Image / Icon / Cloudinary CDN Asset] ──► Cache-First (Fallback to Network)
         │
         ├─── [CSS / JS / Web Fonts] ────────────────────────► Stale-While-Revalidate
         │
         └─── [Storefront Page Navigation] ──────────────────► Network-First (Fallback to offline.php)
```

---

## 2. Strict Zero-Cache Security Policy

The following sensitive paths are explicitly bypassed by the Service Worker to prevent session leaks, stale pricing, and authentication replay:

- `/admin/*` (All administrative ERP routes)
- `/login.php`, `/register.php`, `/logout.php` (Authentication endpoints)
- `/checkout.php`, `/process_checkout.php` (Checkout processors)
- `/activate.php`, `/license_status.php` (Licensing gatekeeper screens)
- `/account.php`, `/orders.php`, `/order-details.php` (Private customer records)
- `/api/v1/pos/*` (POS transactional endpoints)

---

## 3. Offline Experience & Reconnection

When a shopper loses network connectivity while browsing:
1. Cached category cards, stylesheets, and product images render seamlessly from disk cache.
2. If navigating to an uncached dynamic page, the Service Worker displays a branded, graceful `offline.php` template with automated auto-retry on network reconnection (`window.addEventListener('online')`).
