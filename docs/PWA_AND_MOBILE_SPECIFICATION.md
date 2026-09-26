# Progressive Web App (PWA) & Mobile Specification

## 1. Overview & Mobile-First Strategy

The GroCo Grocery Store is built with a responsive, mobile-first design strategy, functioning both as a web application and as an installable Progressive Web App (PWA). This ensures instant app-like launching on Android and iOS devices without requiring an app store download.

---

## 2. Web App Manifest (`public/manifest.json`)

```json
{
  "name": "GroCo Grocery Store",
  "short_name": "GroCo",
  "description": "Fresh Grocery Delivery at your doorstep in Bangladesh",
  "start_url": "/?source=pwa",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#2e7d32",
  "orientation": "portrait-primary",
  "lang": "en",
  "icons": [
    {
      "src": "/assets/images/icons/icon-72x72.png",
      "sizes": "72x72",
      "type": "image/png"
    },
    {
      "src": "/assets/images/icons/icon-96x96.png",
      "sizes": "96x96",
      "type": "image/png"
    },
    {
      "src": "/assets/images/icons/icon-128x128.png",
      "sizes": "128x128",
      "type": "image/png"
    },
    {
      "src": "/assets/images/icons/icon-144x144.png",
      "sizes": "144x144",
      "type": "image/png"
    },
    {
      "src": "/assets/images/icons/icon-152x152.png",
      "sizes": "152x152",
      "type": "image/png"
    },
    {
      "src": "/assets/images/icons/icon-192x192.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/assets/images/icons/icon-384x384.png",
      "sizes": "384x384",
      "type": "image/png"
    },
    {
      "src": "/assets/images/icons/icon-512x512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "any maskable"
    }
  ]
}
```

---

## 3. Service Worker Architecture (`public/sw.js`)

### 3.1 Caching Strategy
- **Static Assets (Cache-First)**:
  - Stylesheets (`/assets/css/*.css`)
  - Client JavaScript (`/assets/js/*.js`)
  - Brand Icons, SVGs, Fonts (`/assets/images/*`, Google Fonts)
- **Dynamic HTML Pages (Network-First with Offline Fallback)**:
  - Fetches fresh content from network; if offline, serves cached copy or dedicated `/offline.html` page.
- **Cart & Auth API (Network-Only)**:
  - Always fetches live state to avoid stale pricing or stock discrepancies.

```javascript
const CACHE_NAME = 'groco-v1.4';
const STATIC_ASSETS = [
  '/',
  '/offline.html',
  '/assets/css/main.css',
  '/assets/js/app.js',
  '/assets/js/cart.js',
  '/assets/images/logo.png',
  '/assets/images/placeholder.webp'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((k) => (k !== CACHE_NAME ? caches.delete(k) : null)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => caches.match('/offline.html'))
    );
    return;
  }
  event.respondWith(
    caches.match(event.request).then((res) => res || fetch(event.request))
  );
});
```

---

## 4. Mobile UX & Viewport Breakpoints

| Breakpoint | Screen Width | Key Mobile Layout Adaptations |
| :--- | :--- | :--- |
| **Mobile Portrait** | $< 576\text{ px}$ | Bottom navigation bar (Home, Categories, Cart with badge, Wishlist, Account). Filter drawer replaces sidebar. 2-column compact product grid. |
| **Mobile Landscape / Tablet** | $576\text{ px} - 768\text{ px}$ | 3-column product grid. Slide-out cart drawer. |
| **Desktop / Laptop** | $992\text{ px} - 1200\text{ px}$ | Full megamenu, persistent sticky search bar, 4-column product grid, standard footer. |
| **Wide Screen** | $> 1200\text{ px}$ | 5-column product grid, enhanced high-resolution product gallery zoom. |

---

## 5. Mobile Bottom Navigation Bar Schema
When browsing on mobile screens ($\le 768\text{ px}$), a sticky bottom navigation bar provides instant one-thumb navigation:
1. **Home** (`/`) &mdash; Icon: Home
2. **Categories** (`/categories.php` or opens drawer) &mdash; Icon: Grid
3. **Cart** (Opens mini-cart sheet) &mdash; Icon: Shopping Bag + Dynamic Badge
4. **Wishlist** (`/account/wishlist.php`) &mdash; Icon: Heart
5. **Account** (`/account/profile.php` or `/login.php`) &mdash; Icon: User
