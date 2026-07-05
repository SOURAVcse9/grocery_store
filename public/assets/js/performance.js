/**
 * ==========================================================================
 * public/assets/js/performance.js
 * ==========================================================================
 * Client-side Page Prefetcher & Transition Optimizer.
 * Prefetches internal page links upon hover so clicks load instantly.
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const prefetchedUrls = new Set();
    const currentOrigin = window.location.origin;

    // Listen to mouseover/hover on internal links
    document.body.addEventListener('mouseover', (e) => {
      const link = e.target.closest('a');
      if (!link) return;

      const href = link.getAttribute('href');
      if (!href) return;

      // Ensure it is an internal relative link and not an anchor scroll
      if (href.startsWith('#') || href.startsWith('javascript:') || href.includes('logout.php')) {
        return;
      }

      try {
        const url = new URL(href, window.location.href);

        // Only prefetch same-origin URLs to prevent leakage
        if (url.origin !== currentOrigin) return;

        const cleanUrl = url.pathname + url.search;

        if (prefetchedUrls.has(cleanUrl)) return;

        // Create prefetch tag
        const linkTag = document.createElement('link');
        linkTag.rel = 'prefetch';
        linkTag.href = url.href;

        document.head.appendChild(linkTag);
        prefetchedUrls.add(cleanUrl);

      } catch (err) {
        // Silently swallow parsing errors for safe fallbacks
      }
    }, { passive: true });
  });
})();
