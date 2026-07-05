/**
 * ==========================================================================
 * public/assets/js/compare.js
 * ==========================================================================
 * Manage client-side product comparison sheet triggers:
 *   - Adding products to the comparison list (limit 4)
 *   - Removing products side-by-side (reflowing columns)
 *   - Adding items to cart from comparison table cells
 *   - Clearing comparison lists
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // ---------------------------------------------------------------------
    // 1. Global Add to Compare list Click Trigger
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-add-to-compare, .btn-compare');
      if (!btn) return;

      e.preventDefault();
      const productId = btn.dataset.productId;
      if (!productId) return;

      btn.style.pointerEvents = 'none';

      try {
        const json = await window.apiPost('ajax/compare.php', {
          action: 'add',
          product_id: productId
        });

        btn.style.pointerEvents = '';

        if (json.success) {
          window.showToast?.(json.message, 'success');
          btn.classList.add('in-compare');
        }
      } catch (err) {
        btn.style.pointerEvents = '';
      }
    });

    // ---------------------------------------------------------------------
    // 2. Remove Product from Comparison Matrix
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-compare-remove');
      if (!btn) return;

      const productId = btn.dataset.productId;
      if (!productId) return;

      btn.disabled = true;

      const json = await window.apiPost('ajax/compare.php', {
        action: 'remove',
        product_id: productId
      });

      if (json.success) {
        window.showToast?.(json.message, 'success');
        refreshCompareTable();
      } else {
        btn.disabled = false;
      }
    });

    // ---------------------------------------------------------------------
    // 3. Add to Cart from Comparison
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-compare-cart');
      if (!btn) return;

      const productId = btn.dataset.productId;
      if (!productId) return;

      btn.disabled = true;
      const origHtml = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

      const json = await window.apiPost('ajax/add_to_cart.php', {
        product_id: productId,
        quantity: '1'
      });

      btn.disabled = false;
      btn.innerHTML = origHtml;

      if (json.success) {
        window.showToast?.(json.message, 'success');
        
        // Update header cart count
        const cartBadge = document.getElementById('cartCount');
        if (cartBadge && json.data?.cart_count !== undefined) {
          cartBadge.textContent = json.data.cart_count.toString();
        }
      }
    });

    // ---------------------------------------------------------------------
    // 4. Clear Comparison Matrix
    // ---------------------------------------------------------------------
    const btnClear = document.querySelector('.btn-clear-compare');
    btnClear?.addEventListener('click', async () => {
      if (confirm('Are you sure you want to clear your product comparison matrix?')) {
        btnClear.disabled = true;

        const json = await window.apiPost('ajax/compare.php', {
          action: 'clear'
        });

        if (json.success) {
          window.showToast?.(json.message, 'success');
          refreshCompareTable();
          btnClear.remove();
        } else {
          btnClear.disabled = false;
        }
      }
    });

    // Helper: AJAX refresh matrix
    async function refreshCompareTable() {
      const container = document.getElementById('compareTableContainer');
      if (!container) return;

      try {
        const res = await fetch('api/compare.php', {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();

        if (json.success && json.data) {
          container.innerHTML = json.data.html ?? '';
          
          // If empty, remove the top clear button if still present
          if (json.data.compare_count === 0) {
            document.querySelector('.btn-clear-compare')?.remove();
          }
        }
      } catch (err) {
        console.error('Failed to reload comparison matrix:', err);
      }
    }

  });
})();
