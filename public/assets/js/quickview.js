/**
 * ==========================================================================
 * public/assets/js/quickview.js
 * ==========================================================================
 * Quick View AJAX Modal loader and controller.
 * Spawns a modal dynamically and handles gallery updates, quantity adjustments,
 * and buy actions inside the modal.
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // 1. Create/retrieve modal DOM elements
    let modal = document.getElementById('quickviewModal');
    
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'quickviewModal';
      modal.className = 'qv-modal';
      modal.innerHTML = `
        <div class="qv-modal-overlay" id="qvModalOverlay"></div>
        <div class="qv-modal-container">
          <button type="button" class="qv-modal-close" id="qvModalClose" aria-label="Close Modal">&times;</button>
          <div class="qv-modal-body" id="qvModalBody">
            <div class="qv-loading-spinner">
              <i class="fas fa-spinner fa-spin fa-2x"></i>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
    }

    const overlay = document.getElementById('qvModalOverlay');
    const closeBtn = document.getElementById('qvModalClose');
    const body = document.getElementById('qvModalBody');

    // 2. Open Modal function
    function openModal() {
      modal.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    }

    // 3. Close Modal function
    function closeModal() {
      modal.classList.remove('is-open');
      document.body.style.overflow = '';
      setTimeout(() => {
        body.innerHTML = `
          <div class="qv-loading-spinner">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
          </div>
        `;
      }, 300); // clear content after transition
    }

    // Attach close triggers
    closeBtn?.addEventListener('click', closeModal);
    overlay?.addEventListener('click', closeModal);
    
    // Close on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal();
      }
    });

    // 4. Attach quickview trigger to document body (event delegation)
    document.body.addEventListener('click', async (e) => {
      const trigger = e.target.closest('.btn-quickview');
      if (!trigger) return;

      e.preventDefault();
      const productId = trigger.dataset.productId;
      if (!productId) return;

      openModal();

      try {
        const res = await fetch(`ajax/quickview.php?product_id=${encodeURIComponent(productId)}`, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        const json = await res.json();
        
        if (json.success && json.data?.html) {
          body.innerHTML = json.data.html;
          initModalInteractions(body);
        } else {
          window.showToast?.(json.message || 'Failed to load details.', 'error');
          closeModal();
        }
      } catch (err) {
        window.showToast?.('Error loading product details.', 'error');
        closeModal();
      }
    });

    // 5. Initialize interactions inside the modal
    function initModalInteractions(container) {
      // Gallery Thumbnail Switching
      const mainImage = container.querySelector('#qvMainImage');
      const thumbBtns = container.querySelectorAll('.qv-thumb-btn');

      thumbBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
          // Remove active class from all
          thumbBtns.forEach((b) => b.classList.remove('active'));
          btn.classList.add('active');

          const newUrl = btn.dataset.largeUrl;
          if (mainImage && newUrl) {
            mainImage.src = newUrl;
          }
        });
      });

      // Quantity adjustments
      const qtyInput = container.querySelector('#qvQtyInput');
      const btnMinus = container.querySelector('.qv-qty-minus');
      const btnPlus = container.querySelector('.qv-qty-plus');

      if (qtyInput && btnMinus && btnPlus) {
        const maxVal = parseInt(qtyInput.getAttribute('max') || '999');

        btnMinus.addEventListener('click', () => {
          let val = parseInt(qtyInput.value);
          if (val > 1) {
            qtyInput.value = (val - 1).toString();
          }
        });

        btnPlus.addEventListener('click', () => {
          let val = parseInt(qtyInput.value);
          if (val < maxVal) {
            qtyInput.value = (val + 1).toString();
          }
        });
      }

      // Add to Cart inside Quick View
      const addToCartBtn = container.querySelector('.qv-btn-add');
      addToCartBtn?.addEventListener('click', async () => {
        const productId = addToCartBtn.dataset.productId;
        const quantity = qtyInput ? qtyInput.value : '1';

        const originalHtml = addToCartBtn.innerHTML;
        addToCartBtn.disabled = true;
        addToCartBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

        const json = await window.apiPost('ajax/add_to_cart.php', {
          product_id: productId,
          quantity: quantity
        });

        addToCartBtn.disabled = false;
        addToCartBtn.innerHTML = originalHtml;

        if (json.success) {
          window.showToast?.(json.message, 'success');
          // Update cart badge in header
          const badge = document.getElementById('cartCount');
          if (badge && json.data?.cart_count !== undefined) {
            badge.textContent = json.data.cart_count.toString();
          }
          closeModal();
        }
      });

      // Buy Now inside Quick View
      const buyNowBtn = container.querySelector('.qv-btn-buy');
      buyNowBtn?.addEventListener('click', async () => {
        const productId = buyNowBtn.dataset.productId;
        const quantity = qtyInput ? qtyInput.value : '1';

        const originalHtml = buyNowBtn.innerHTML;
        buyNowBtn.disabled = true;
        buyNowBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirecting...';

        const json = await window.apiPost('ajax/add_to_cart.php', {
          product_id: productId,
          quantity: quantity
        });

        if (json.success) {
          // Redirect to checkout page directly
          window.location.href = 'checkout.php';
        } else {
          buyNowBtn.disabled = false;
          buyNowBtn.innerHTML = originalHtml;
        }
      });
    }
  });
})();
