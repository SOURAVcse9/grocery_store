/**
 * ==========================================================================
 * app.js — Core site behavior (loaded on every page)
 * ==========================================================================
 * Responsibilities:
 *   - Sticky header shadow-on-scroll
 *   - Mobile off-canvas menu open/close
 *   - Desktop user-menu dropdown (tap-to-toggle on touch devices)
 *   - Back-to-top button
 *   - Live search suggestions (AJAX -> ajax/search.php)
 *
 * Feature-specific behavior (cart, wishlist, quickview, checkout...) lives
 * in its own file (cart.js, wishlist.js, ...) and is loaded only on the
 * pages that need it.
 * ==========================================================================
 */

(function () {
  'use strict';

  // ---------------------------------------------------------------------
  // Sticky header shadow
  // ---------------------------------------------------------------------
  const header = document.getElementById('siteHeader');
  if (header) {
    const onScroll = () => {
      header.classList.toggle('is-scrolled', window.scrollY > 8);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  // ---------------------------------------------------------------------
  // Mobile off-canvas menu
  // ---------------------------------------------------------------------
  const mobileMenu = document.getElementById('mobileMenu');
  const mobileOverlay = document.getElementById('mobileOverlay');
  const openMenuBtn = document.getElementById('openMenuBtn');
  const closeMenuBtn = document.getElementById('closeMenuBtn');

  function openMobileMenu() {
    mobileMenu?.classList.add('is-open');
    mobileOverlay?.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function closeMobileMenu() {
    mobileMenu?.classList.remove('is-open');
    mobileOverlay?.classList.remove('is-open');
    document.body.style.overflow = '';
  }

  openMenuBtn?.addEventListener('click', openMobileMenu);
  closeMenuBtn?.addEventListener('click', closeMobileMenu);
  mobileOverlay?.addEventListener('click', closeMobileMenu);

  // ---------------------------------------------------------------------
  // User dropdown (click-to-toggle so it also works on touch devices;
  // CSS :hover already handles desktop mouse users)
  // ---------------------------------------------------------------------
  document.querySelectorAll('.user-menu').forEach((menu) => {
    const trigger = menu.querySelector('.user-menu-trigger');
    trigger?.addEventListener('click', (e) => {
      e.stopPropagation();
      menu.classList.toggle('is-open');
    });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.user-menu.is-open').forEach((m) => m.classList.remove('is-open'));
  });

  // ---------------------------------------------------------------------
  // Back to top
  // ---------------------------------------------------------------------
  const backToTop = document.getElementById('backToTop');
  if (backToTop) {
    window.addEventListener(
      'scroll',
      () => backToTop.classList.toggle('is-visible', window.scrollY > 400),
      { passive: true }
    );
    backToTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }

  // ---------------------------------------------------------------------
  // Live search suggestions
  // ---------------------------------------------------------------------
  const searchInput = document.getElementById('smartSearch');
  const searchSuggestions = document.getElementById('searchSuggestions');
  let searchDebounce;
  let searchAbortController;

  function renderSuggestions(products, query) {
    if (!searchSuggestions) return;

    if (!products.length) {
      searchSuggestions.innerHTML = `<div class="suggestion-empty">No products found for "${escapeHtml(query)}"</div>`;
      searchSuggestions.classList.add('is-open');
      return;
    }

    searchSuggestions.innerHTML = products
      .map(
        (p) => `
        <a href="product.php?slug=${encodeURIComponent(p.slug)}">
          <img src="${p.thumbnail}" alt="${escapeHtml(p.name)}" loading="lazy">
          <span>${escapeHtml(p.name)}</span>
        </a>`
      )
      .join('');
    searchSuggestions.classList.add('is-open');
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  if (searchInput && searchSuggestions) {
    searchInput.addEventListener('input', (e) => {
      const query = e.target.value.trim();
      clearTimeout(searchDebounce);

      if (query.length < 2) {
        searchSuggestions.classList.remove('is-open');
        return;
      }

      searchDebounce = setTimeout(async () => {
        searchAbortController?.abort();
        searchAbortController = new AbortController();

        try {
          const res = await fetch(`ajax/search.php?q=${encodeURIComponent(query)}`, {
            signal: searchAbortController.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          });
          const json = await res.json();
          renderSuggestions(json.data?.products ?? [], query);
        } catch (err) {
          if (err.name !== 'AbortError') {
            searchSuggestions.classList.remove('is-open');
          }
        }
      }, 300);
    });

    document.addEventListener('click', (e) => {
      if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
        searchSuggestions.classList.remove('is-open');
      }
    });
  }

  // ---------------------------------------------------------------------
  // Shared fetch helper for feature JS files (cart.js, wishlist.js, ...)
  // Centralizes CSRF header + JSON parsing + error toast so every feature
  // module stays short and consistent.
  // ---------------------------------------------------------------------
  window.apiPost = async function apiPost(url, payload = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const body = new URLSearchParams({ ...payload, csrf_token: csrfToken });

    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body,
      });
      const json = await res.json();

      if (!json.success && json.message) {
        window.showToast?.(json.message, 'error');
      }

      return json;
    } catch (err) {
      window.showToast?.('Network error. Please try again.', 'error');
      return { success: false, message: 'Network error', data: {} };
    }
  };
})();
