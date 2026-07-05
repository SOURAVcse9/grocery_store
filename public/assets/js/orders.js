/**
 * ==========================================================================
 * public/assets/js/orders.js
 * ==========================================================================
 * Handle client-side actions for orders listing and tracking:
 *   - AJAX reordering of items from past purchases
 *   - Invoice printing triggers
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // ---------------------------------------------------------------------
    // 1. AJAX Reorder Products
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-reorder-products');
      if (!btn) return;

      const orderId = btn.dataset.orderId;
      if (!orderId) return;

      btn.disabled = true;
      const originalHtml = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reordering...';

      try {
        const json = await window.apiPost(window.location.pathname, {
          action: 'reorder',
          order_id: orderId
        });

        if (json.success) {
          window.showToast?.(json.message, 'success');
          
          // Update cart badge if returned
          const badge = document.getElementById('cartCount');
          if (badge && json.data?.cart_count !== undefined) {
            badge.textContent = json.data.cart_count.toString();
          }

          // Redirect to cart page after 1 second
          setTimeout(() => {
            window.location.href = 'cart.php';
          }, 1000);
        } else {
          btn.disabled = false;
          btn.innerHTML = originalHtml;
          window.showToast?.(json.message || 'Failed to reorder items.', 'error');
        }
      } catch (err) {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        window.showToast?.('Connection error. Please try again.', 'error');
      }
    });

    // ---------------------------------------------------------------------
    // 2. Print Invoice Trigger
    // ---------------------------------------------------------------------
    const btnPrint = document.getElementById('btnPrintInvoice');
    btnPrint?.addEventListener('click', () => {
      window.print();
    });

  });
})();
