/**
 * ==========================================================================
 * public/sw.js — PWA Service Worker Cache Controller
 * ==========================================================================
 * Manages caching strategies, asset versions, background synchronizations,
 * and offline fallback layouts.
 * ==========================================================================
 */

const CACHE_VERSION = 'v1';
const CACHE_STATIC_NAME = `groco-static-${CACHE_VERSION}`;
const CACHE_DYNAMIC_NAME = `groco-dynamic-${CACHE_VERSION}`;
const CACHE_IMAGE_NAME = `groco-images-${CACHE_VERSION}`;

// Static resources cached immediately during installation
const STATIC_ASSETS = [
  'offline.php',
  'assets/css/style.css',
  'assets/css/header.css',
  'assets/css/footer.css',
  'assets/css/components.css',
  'assets/css/pwa.css',
  'assets/css/performance.css',
  'assets/js/app.js',
  'assets/js/pwa.js',
  'assets/js/lazyload.js',
  'assets/js/performance.js',
  'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'
];

// URLs that must NEVER be cached (e.g. admin panels, checkout processors, auth endpoints)
const BYPASS_CACHE_URLS = [
  'login.php',
  'register.php',
  'logout.php',
  'checkout.php',
  'process_checkout.php',
  'thank-you.php',
  'admin/',
  'ajax/update_cart.php',
  'ajax/add_to_cart.php',
  'ajax/remove_cart.php',
  'ajax/notification.php',
  'ajax/wishlist.php',
  'ajax/compare.php'
];

// ---------------------------------------------------------------------
// 1. Install Event
// ---------------------------------------------------------------------
self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_STATIC_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    }).then(() => {
      return self.skipWaiting();
    })
  );
});

// ---------------------------------------------------------------------
// 2. Activate Event (Automatic Cache Cleanup)
// ---------------------------------------------------------------------
self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (
            key !== CACHE_STATIC_NAME &&
            key !== CACHE_DYNAMIC_NAME &&
            key !== CACHE_IMAGE_NAME
          ) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => {
      return self.clients.claim();
    })
  );
});

// ---------------------------------------------------------------------
// 3. Fetch Event Routing Rules
// ---------------------------------------------------------------------
self.addEventListener('fetch', (e) => {
  const requestUrl = e.request.url;

  // Bypass cache entirely for POST requests, checkout pages, and admin controls
  if (
    e.request.method !== 'GET' ||
    BYPASS_CACHE_URLS.some((path) => requestUrl.includes(path))
  ) {
    e.respondWith(fetch(e.request));
    return;
  }

  // A. Image Assets: Cache-First
  if (
    e.request.destination === 'image' ||
    requestUrl.includes('/uploads/') ||
    requestUrl.includes('/assets/images/')
  ) {
    e.respondWith(
      caches.match(e.request).then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }
        return fetch(e.request).then((networkResponse) => {
          if (!networkResponse || networkResponse.status !== 200) {
            return networkResponse;
          }
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_IMAGE_NAME).then((cache) => {
            cache.put(e.request, responseToCache);
          });
          return networkResponse;
        }).catch(() => {
          // Return default logo placeholder if image load fails offline
          return caches.match('assets/images/icons/icon-192x192.png');
        });
      })
    );
    return;
  }

  // B. Static Code Files (JS / CSS / Fonts): Cache-First
  if (
    e.request.destination === 'script' ||
    e.request.destination === 'style' ||
    e.request.destination === 'font' ||
    requestUrl.includes('.js') ||
    requestUrl.includes('.css')
  ) {
    e.respondWith(
      caches.match(e.request).then((cachedResponse) => {
        if (cachedResponse) {
          return cachedResponse;
        }
        return fetch(e.request).then((networkResponse) => {
          if (!networkResponse || networkResponse.status !== 200) {
            return networkResponse;
          }
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_STATIC_NAME).then((cache) => {
            cache.put(e.request, responseToCache);
          });
          return networkResponse;
        });
      })
    );
    return;
  }

  // C. API requests: Network-First with Cache Fallback
  if (requestUrl.includes('/api/')) {
    e.respondWith(
      fetch(e.request).then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200) {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_DYNAMIC_NAME).then((cache) => {
            cache.put(e.request, responseToCache);
          });
        }
        return networkResponse;
      }).catch(() => {
        return caches.match(e.request);
      })
    );
    return;
  }

  // D. Page Navigations: Network-First with Offline Fallback
  if (e.request.mode === 'navigate') {
    e.respondWith(
      fetch(e.request).catch(() => {
        return caches.match('offline.php');
      })
    );
    return;
  }
});
