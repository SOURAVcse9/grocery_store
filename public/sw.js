/**
 * ==============================================================================
 * public/sw.js — GroCo Modern PWA Service Worker (v2.0.0)
 * ==============================================================================
 * High-performance service worker with cache versioning, network-first strategy
 * for live store data, cache-first for immutable static bundles and icons,
 * and zero caching of sensitive admin/checkout/payment routes.
 * ==============================================================================
 */

const CACHE_VERSION = 'v2.0.0';
const CACHE_STATIC_NAME = `groco-static-${CACHE_VERSION}`;
const CACHE_DYNAMIC_NAME = `groco-dynamic-${CACHE_VERSION}`;
const CACHE_IMAGE_NAME = `groco-images-${CACHE_VERSION}`;

// Core static app shell pre-cached on install
const STATIC_ASSETS = [
  'offline.php',
  'manifest.json',
  'assets/css/style.css',
  'assets/css/header.css',
  'assets/css/footer.css',
  'assets/css/components.css',
  'assets/css/pwa.css',
  'assets/js/theme.js',
  'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'
];

// Routes and patterns that must NEVER be cached by the Service Worker
const SENSITIVE_NO_CACHE_ROUTES = [
  'admin/',
  'login.php',
  'register.php',
  'logout.php',
  'checkout.php',
  'process_checkout.php',
  'process_register.php',
  'thank-you.php',
  'activate.php',
  'license_status.php',
  'account.php',
  'orders.php',
  'order-details.php',
  'api/v1/pos/',
  'ajax/pos/'
];

// 1. Service Worker Install Lifecycle
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_STATIC_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    }).then(() => {
      return self.skipWaiting();
    })
  );
});

// 2. Service Worker Activation & Legacy Cache Eviction
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((name) => {
          if (
            name !== CACHE_STATIC_NAME &&
            name !== CACHE_DYNAMIC_NAME &&
            name !== CACHE_IMAGE_NAME
          ) {
            return caches.delete(name);
          }
        })
      );
    }).then(() => {
      return self.clients.claim();
    })
  );
});

// 3. Request Interception & Caching Strategies
self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = request.url;

  // Never intercept non-GET requests or sensitive routes
  if (
    request.method !== 'GET' ||
    SENSITIVE_NO_CACHE_ROUTES.some((route) => url.includes(route))
  ) {
    event.respondWith(fetch(request));
    return;
  }

  // Strategy A: Static Images & Icons (Cache-First with Network Fallback)
  if (
    request.destination === 'image' ||
    url.includes('/uploads/') ||
    url.includes('/assets/images/') ||
    url.includes('res.cloudinary.com')
  ) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) return cached;
        return fetch(request).then((response) => {
          if (response && response.status === 200) {
            const clone = response.clone();
            caches.open(CACHE_IMAGE_NAME).then((cache) => cache.put(request, clone));
          }
          return response;
        }).catch(() => {
          return caches.match('offline.php');
        });
      })
    );
    return;
  }

  // Strategy B: CSS, JavaScript, and Web Fonts (Stale-While-Revalidate)
  if (
    request.destination === 'style' ||
    request.destination === 'script' ||
    request.destination === 'font' ||
    url.endsWith('.css') ||
    url.endsWith('.js')
  ) {
    event.respondWith(
      caches.match(request).then((cached) => {
        const fetchPromise = fetch(request).then((networkResponse) => {
          if (networkResponse && networkResponse.status === 200) {
            const clone = networkResponse.clone();
            caches.open(CACHE_STATIC_NAME).then((cache) => cache.put(request, clone));
          }
          return networkResponse;
        });
        return cached || fetchPromise;
      })
    );
    return;
  }

  // Strategy C: Storefront Navigations (Network-First with Offline Fallback Screen)
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => {
        return caches.match('offline.php');
      })
    );
    return;
  }

  // Default: Network Fetch
  event.respondWith(fetch(request));
});
