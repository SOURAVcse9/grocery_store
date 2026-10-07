/**
 * ==========================================================================
 * public/assets/js/cart.js
 * ==========================================================================
 * Centralized Shopping Cart & Mini-Cart Engine:
 *   - Slide-out Floating Mini-Cart drawer creation & lifecycle management
 *   - Global AJAX Add to Cart & Buy Now (handles cards, details, quickview)
 *   - Quantity Updates & Removals (for both Cart Page & Mini-Cart drawer)
 *   - Coupon application & removal handling
 *   - Real-time DOM totals recalculation (subtotal, delivery, coupon, grand total)
 *   - Protection against double-execution and rapid concurrent clicks
 * ==========================================================================
 */

(function () {
  'use strict';

  // Prevent multiple script registrations
  if (window.__grocoCartLoaded) return;
  window.__grocoCartLoaded = true;

  function initCart() {
    // ---------------------------------------------------------------------
    // 1. Initialize Floating Mini-Cart Drawer
    // ---------------------------------------------------------------------
    let drawer = document.getElementById('floatingMiniCart');
    let drawerOverlay = document.getElementById('miniCartOverlay');

    if (!drawer) {
      drawer = document.createElement('div');
      drawer.id = 'floatingMiniCart';
      drawer.className = 'mini-cart-drawer';
      drawer.innerHTML = `
        <div class="mini-cart-header">
          <span class="mini-cart-title"><i class="fas fa-shopping-basket"></i> Cart</span>
          <button type="button" class="mini-cart-close-btn" id="miniCartCloseBtn" aria-label="Close Cart">&times;</button>
        </div>
        <div class="mini-cart-items-wrapper" style="display:flex;align-items:center;justify-content:center;height:100%;">
          <i class="fas fa-spinner fa-spin fa-2x" style="color:var(--color-primary);"></i>
        </div>
      `;

      drawerOverlay = document.createElement('div');
      drawerOverlay.id = 'miniCartOverlay';
      drawerOverlay.className = 'mini-cart-overlay';

      document.body.appendChild(drawer);
      document.body.appendChild(drawerOverlay);
    }

    function isCurrentPageCart() {
      return !!document.getElementById('cartPageContent') || !!document.getElementById('cartPageSubtotal');
    }

    function toggleMiniCart(open) {
      if (open) {
        drawer.classList.add('is-open');
        drawerOverlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        refreshMiniCart();
      } else {
        drawer.classList.remove('is-open');
        drawerOverlay.classList.remove('is-open');
        document.body.style.overflow = '';
      }
    }

    // Attach Close handlers
    drawer.addEventListener('click', (e) => {
      if (e.target.closest('#miniCartCloseBtn')) {
        toggleMiniCart(false);
      }
    });
    drawerOverlay.addEventListener('click', () => toggleMiniCart(false));

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
        toggleMiniCart(false);
      }
    });

    // Intercept clicks on the header cart icon link
    document.addEventListener('click', (e) => {
      const headerCartBtn = e.target.closest('a[href*="cart.php"].icon-link, a[href$="/cart"].icon-link, a[href$="/cart/"].icon-link');
      if (headerCartBtn) {
        if (!isCurrentPageCart()) {
          e.preventDefault();
          toggleMiniCart(true);
        }
      }
    });

    // ---------------------------------------------------------------------
    // 2. Fetch and Refresh Cart Data (Dynamic totals calculations)
    // ---------------------------------------------------------------------
    function formatCurrency(amount) {
      const num = parseFloat(amount);
      return '৳' + (isNaN(num) ? '0.00' : num.toFixed(2));
    }

    async function refreshMiniCart() {
      try {
        const apiUrl = window.resolveApiUrl ? window.resolveApiUrl('api/cart.php') : 'api/cart.php';
        const res = await fetch(apiUrl, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();

        if (json.success && json.data) {
          // Update Drawer Content
          drawer.innerHTML = json.data.html;

          // Update Header Cart Badge
          const badge = document.getElementById('cartCount');
          if (badge && json.data.cart_count !== undefined) {
            badge.textContent = json.data.cart_count.toString();
          }

          // If on the Cart Page, update page summaries too
          if (isCurrentPageCart()) {
            updateCartPageSummary(json.data);
          }
        }
      } catch (err) {
        console.error('Error refreshing mini cart:', err);
      }
    }

    function updateCartPageSummary(data) {
      const subtotalEl = document.getElementById('cartPageSubtotal');
      const discountRow = document.getElementById('cartPageDiscountRow');
      const discountEl = document.getElementById('cartPageDiscount');
      const deliveryEl = document.getElementById('cartPageDelivery');
      const totalEl = document.getElementById('cartPageTotal');

      if (subtotalEl) subtotalEl.textContent = formatCurrency(data.subtotal);
      if (deliveryEl) deliveryEl.textContent = formatCurrency(data.delivery_charge);
      if (totalEl) totalEl.textContent = formatCurrency(data.grand_total);

      if (discountRow && discountEl) {
        if (data.discount_amount > 0) {
          discountRow.style.display = 'flex';
          discountEl.textContent = '-' + formatCurrency(data.discount_amount);
        } else {
          discountRow.style.display = 'none';
        }
      }

      // Check if cart is now empty on the page
      if (data.cart_count === 0) {
        const cartTableSection = document.getElementById('cartPageContent');
        const emptyStateSection = document.getElementById('cartPageEmpty');
        if (cartTableSection && emptyStateSection) {
          cartTableSection.style.display = 'none';
          emptyStateSection.style.display = 'block';
        }
      }
    }

    window.refreshMiniCart = refreshMiniCart;
    window.toggleMiniCart = toggleMiniCart;

    // ---------------------------------------------------------------------
    // 3. Global AJAX Add to Cart & Buy Now
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-add-cart, .detail-btn-add, .qv-btn-add, .btn-buy-now, .detail-btn-buy, .qv-btn-buy');
      if (!btn) return;

      // Ignore buttons explicitly flagged
      if (btn.id === 'btnFbtAddAll') return;

      e.preventDefault();

      // Guard against rapid duplicate clicks
      if (btn.disabled || btn.dataset.busy === '1') return;

      const productId = btn.dataset.productId || btn.closest('[data-id]')?.dataset.id || btn.closest('[data-product-id]')?.dataset.productId;
      if (!productId) return;

      const isBuyNow = btn.classList.contains('btn-buy-now') || btn.classList.contains('detail-btn-buy') || btn.classList.contains('qv-btn-buy');

      // Determine quantity scoped to button context
      const container = btn.closest('.product-detail-actions, .product-card-footer, .product-actions, .qv-actions, .product-detail-layout') || document;
      const qtyInput = container.querySelector('#detailQtyInput, #qvQtyInput, .detail-qty-input, .qv-qty-input, #productQtyInput')
        || document.getElementById('detailQtyInput')
        || document.getElementById('qvQtyInput');

      let quantity = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
      if (quantity < 1) quantity = 1;

      // Loading state
      btn.dataset.busy = '1';
      btn.disabled = true;
      const origHtml = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

      try {
        const json = await window.apiPost('ajax/add_to_cart.php', {
          product_id: productId,
          quantity: quantity
        });

        btn.disabled = false;
        btn.dataset.busy = '0';
        btn.innerHTML = origHtml;

        if (json.success) {
          window.showToast?.(json.message, 'success');

          // Update header cart badge
          const badge = document.getElementById('cartCount');
          if (badge && json.data?.cart_count !== undefined) {
            badge.textContent = json.data.cart_count.toString();
          }

          if (isBuyNow) {
            const checkoutUrl = window.resolveApiUrl ? window.resolveApiUrl('checkout.php') : 'checkout.php';
            window.location.href = checkoutUrl;
          } else {
            // Close QuickView modal if active
            const qvModal = document.getElementById('quickviewModal');
            if (qvModal && qvModal.classList.contains('is-open')) {
              qvModal.classList.remove('is-open');
              document.body.style.overflow = '';
            }

            // Open or refresh mini cart drawer on non-cart pages
            if (!isCurrentPageCart()) {
              if (window.innerWidth > 640) {
                toggleMiniCart(true);
              } else {
                refreshMiniCart();
              }
            } else {
              // Reload or refresh on cart page
              window.location.reload();
            }
          }
        } else {
          window.showToast?.(json.message || 'Failed to add item to cart.', 'error');
        }
      } catch (err) {
        btn.disabled = false;
        btn.dataset.busy = '0';
        btn.innerHTML = origHtml;
        window.showToast?.('Connection error. Please try again.', 'error');
      }
    });

    // ---------------------------------------------------------------------
    // 4. Quantity Adjusters & Removals (Works on Cart Page AND Mini-Cart Drawer)
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      // Quantity Decrement (-)
      const minus = e.target.closest('.cart-qty-minus');
      if (minus) {
        e.preventDefault();
        const container = minus.closest('.cart-qty-adjuster, .mini-cart-adjuster, .cart-item-qty') || minus.parentElement;
        const input = container ? container.querySelector('.cart-qty-input') : null;
        const productId = minus.dataset.productId || minus.closest('[data-product-id]')?.dataset.productId;

        if (!input || !productId) return;
        let val = parseInt(input.value, 10) || 1;

        if (val > 1) {
          val--;
          input.value = val.toString();
          await updateCartQty(productId, val, input);
        }
        return;
      }

      // Quantity Increment (+)
      const plus = e.target.closest('.cart-qty-plus');
      if (plus) {
        e.preventDefault();
        const container = plus.closest('.cart-qty-adjuster, .mini-cart-adjuster, .cart-item-qty') || plus.parentElement;
        const input = container ? container.querySelector('.cart-qty-input') : null;
        const productId = plus.dataset.productId || plus.closest('[data-product-id]')?.dataset.productId;

        if (!input || !productId) return;
        const maxVal = parseInt(input.getAttribute('max') || '999', 10);
        let val = parseInt(input.value, 10) || 1;

        if (val < maxVal) {
          val++;
          input.value = val.toString();
          await updateCartQty(productId, val, input);
        } else {
          window.showToast?.(`Maximum available stock is ${maxVal} units.`, 'warning');
        }
        return;
      }

      // Item Delete / Remove Button
      const deleteBtn = e.target.closest('.btn-remove-cart-item');
      if (deleteBtn) {
        e.preventDefault();
        const productId = deleteBtn.dataset.productId || deleteBtn.closest('[data-product-id]')?.dataset.productId;
        if (!productId) return;

        if (confirm('Are you sure you want to remove this item from your cart?')) {
          await removeCartItem(productId, deleteBtn);
        }
        return;
      }
    });

    // Helper: Update Quantity via AJAX
    async function updateCartQty(productId, qty, inputEl) {
      const originalVal = parseInt(inputEl.dataset.original || qty.toString(), 10);

      const json = await window.apiPost('ajax/update_cart.php', {
        product_id: productId,
        quantity: qty
      });

      if (json.success) {
        inputEl.dataset.original = qty.toString();

        // Update all line items in DOM with this productId
        document.querySelectorAll(`.cart-item-row[data-product-id="${productId}"]`).forEach((row) => {
          const unitPrice = parseFloat(row.dataset.price);
          const lineTotalEl = row.querySelector('.cart-item-line-total');
          if (lineTotalEl && !isNaN(unitPrice)) {
            lineTotalEl.textContent = formatCurrency(unitPrice * qty);
          }
        });

        refreshMiniCart();
      } else {
        inputEl.value = originalVal.toString();
        window.showToast?.(json.message || 'Could not update quantity.', 'error');
        refreshMiniCart();
      }
    }

    // Helper: Remove Item via AJAX
    async function removeCartItem(productId, btnEl) {
      const json = await window.apiPost('ajax/remove_cart.php', {
        product_id: productId
      });

      if (json.success) {
        window.showToast?.(json.message, 'success');

        // Animate out row if on Cart Page
        const pageRow = document.querySelector(`.cart-item-row[data-product-id="${productId}"]`);
        if (pageRow) {
          pageRow.style.opacity = '0';
          pageRow.style.transform = 'translateX(-20px)';
          pageRow.style.transition = 'all 250ms ease';
          setTimeout(() => {
            pageRow.remove();
            refreshMiniCart();
          }, 250);
        } else {
          refreshMiniCart();
        }
      }
    }

    // ---------------------------------------------------------------------
    // 5. Coupon Application Form (Cart Page)
    // ---------------------------------------------------------------------
    const couponForm = document.getElementById('couponForm');
    if (couponForm) {
      couponForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('couponCodeInput');
        const code = input?.value.trim();

        if (!code) return;

        const btn = couponForm.querySelector('button[type="submit"]');
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const json = await window.apiPost('cart.php', {
          apply_coupon: '1',
          coupon_code: code
        });

        btn.disabled = false;
        btn.innerHTML = origText;

        if (json.success) {
          window.showToast?.(json.message, 'success');
          setTimeout(() => window.location.reload(), 400);
        } else {
          window.showToast?.(json.message || 'Invalid coupon code.', 'error');
        }
      });
    }

    // ---------------------------------------------------------------------
    // 6. Coupon Removal (Cart Page)
    // ---------------------------------------------------------------------
    const removeCouponBtn = document.getElementById('btnRemoveCoupon');
    if (removeCouponBtn) {
      removeCouponBtn.addEventListener('click', async (e) => {
        e.preventDefault();

        removeCouponBtn.disabled = true;
        removeCouponBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const json = await window.apiPost('cart.php', {
          remove_coupon: '1'
        });

        if (json.success) {
          window.showToast?.(json.message, 'success');
          setTimeout(() => window.location.reload(), 400);
        }
      });
    }
  }

  // Auto-boot when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCart);
  } else {
    initCart();
  }
})();
