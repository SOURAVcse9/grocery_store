/**
 * ==========================================================================
 * toast.js — Toast notification system
 * ==========================================================================
 * Renders into #toastContainer (see components/toast.php). Exposes a single
 * global `showToast()` used by every other JS module (cart.js, wishlist.js,
 * checkout.js, etc.) so feedback is consistent site-wide.
 * ==========================================================================
 */

(function () {
  'use strict';

  const ICONS = {
    success: 'fa-circle-check',
    error: 'fa-circle-exclamation',
    info: 'fa-circle-info',
    warning: 'fa-triangle-exclamation',
  };

  const TITLES = {
    success: 'Success',
    error: 'Something went wrong',
    info: 'Heads up',
    warning: 'Warning',
  };

  function getContainer() {
    let container = document.getElementById('toastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toastContainer';
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    return container;
  }

  /**
   * showToast(message, type, options)
   * @param {string} message
   * @param {'success'|'error'|'info'|'warning'} type
   * @param {{title?: string, duration?: number}} options
   */
  window.showToast = function showToast(message, type = 'success', options = {}) {
    const container = getContainer();
    const duration = options.duration ?? 3500;
    const title = options.title ?? TITLES[type] ?? TITLES.success;
    const icon = ICONS[type] ?? ICONS.success;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'status');
    toast.innerHTML = `
      <i class="fas ${icon} toast-icon" aria-hidden="true"></i>
      <div class="toast-body">
        <div class="toast-title">${title}</div>
        <div class="toast-message"></div>
      </div>
      <button type="button" class="toast-close" aria-label="Dismiss">&times;</button>
    `;
    // Set message via textContent to avoid any HTML injection from dynamic text.
    toast.querySelector('.toast-message').textContent = message;

    container.appendChild(toast);

    const remove = () => {
      toast.classList.add('is-leaving');
      setTimeout(() => toast.remove(), 200);
    };

    toast.querySelector('.toast-close').addEventListener('click', remove);
    const timer = setTimeout(remove, duration);

    toast.addEventListener('mouseenter', () => clearTimeout(timer));
  };

  // Render any server-side flash messages injected by components/toast.php
  // as a small JSON blob (see that file for the data-flashes attribute).
  document.addEventListener('DOMContentLoaded', function () {
    const seed = document.getElementById('toastContainer');
    if (!seed || !seed.dataset.flashes) return;

    try {
      const flashes = JSON.parse(seed.dataset.flashes);
      Object.values(flashes).forEach((f) => showToast(f.message, f.type || 'success'));
    } catch (e) {
      // Malformed flash payload — fail silently, never break the page.
    }
  });
})();
