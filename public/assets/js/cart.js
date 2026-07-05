/**
 * ==========================================================================
 * public/assets/js/cart.js
 * ==========================================================================
 * Comprehensive Cart operations:
 *   - Slide-out Floating Mini-Cart drawer creation & management
 *   - AJAX Add to Cart, Buy Now, Quantity Updates, and Removals
 *   - Coupon application handling
 *   - Dynamic DOM calculations for subtotal, VAT, delivery, and totals
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // ---------------------------------------------------------------------
    // 1. Initialize Floating Mini-Cart Drawer
    // ---------------------------------------------------------------------
    let drawer = document.getElementById('floatingMiniCart');
    let drawerOverlay = document.getElementById('miniCartOverlay');

    if (!drawer) {
      // Create Drawer Panel
      drawer = document.createElement('div');
      drawer.id = 'floatingMiniCart';
      drawer.className = 'mini-cart-drawer';
      drawer.innerHTML = `
        <div class="mini-cart-body" id="miniCartDrawerBody">
          <div class="mini-cart-loading">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
          </div>
        </div>
      `;

      // Create Overlay
      drawerOverlay = document.createElement('div');
      drawerOverlay.id = 'miniCartOverlay';
      drawerOverlay.className = 'mini-cart-overlay';

      document.body.appendChild(drawer);
      document.body.appendChild(drawerOverlay);
    }

    // Toggle drawer open/close
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

    // Intercept clicks on the header cart icon link
    const headerCartBtn = document.querySelector('a[href*="cart.php"].icon-link');
    if (headerCartBtn && !window.location.pathname.endsWith('cart.php')) {
      headerCartBtn.addEventListener('click', (e) => {
        if (window.innerWidth > 768) {
          e.preventDefault();
          toggleMiniCart(true);
        }
      });
    }

    // ---------------------------------------------------------------------
    // 2. Fetch and Refresh Cart Data (Dynamic totals calculations)
    // ---------------------------------------------------------------------
    async function refreshMiniCart() {
      try {
        const res = await fetch('api/cart.php', {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();

        if (json.success && json.data) {
          // Update Drawer Content
          const body = document.getElementById('miniCartDrawerBody');
          if (body) {
            body.innerHTML = json.data.html;
          }

          // Update Header Cart Badge
          const badge = document.getElementById('cartCount');
          if (badge && json.data.cart_count !== undefined) {
            badge.textContent = json.data.cart_count.toString();
          }

          // If on the Cart Page, update page summaries too
          if (window.location.pathname.endsWith('cart.php')) {
            updateCartPageSummary(json.data);
          }
        }
      } catch (err) {
        console.error('Error refreshing mini cart:', err);
      }
    }

    function formatCurrency(amount) {
      return '৳' + parseFloat(amount).toFixed(2);
    }

    // Update Summary box on public/cart.php
    function updateCartPageSummary(data) {
      const subtotalEl = document.getElementById('cartPageSubtotal');
      const discountRow = document.getElementById('cartPageDiscountRow');
      const discountEl = document.getElementById('cartPageDiscount');
      const deliveryEl = document.getElementById('cartPageDelivery');
      const vatEl = document.getElementById('cartPageVat');
      const totalEl = document.getElementById('cartPageTotal');

      if (subtotalEl) subtotalEl.textContent = formatCurrency(data.subtotal);
      if (deliveryEl) deliveryEl.textContent = formatCurrency(data.delivery_charge);
      if (vatEl) vatEl.textContent = formatCurrency(data.vat_amount);
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

    // Expose refresh to other scripts
    window.refreshMiniCart = refreshMiniCart;

    // ---------------------------------------------------------------------
    // 3. AJAX Add to Cart & Buy Now (Delegate actions globally)
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-add-cart, .btn-add-cart-detail, .qv-btn-add, .btn-buy-now');
      if (!btn) return;

      e.preventDefault();
      
      const productId = btn.dataset.productId || btn.closest('[data-id]')?.dataset.id || btn.closest('[data-product-id]')?.dataset.productId;
      if (!productId) return;

      const isBuyNow = btn.classList.contains('btn-buy-now');
      
      // Determine quantity to add (e.g. from detail page input or default to 1)
      const qtyInput = document.getElementById('productQtyInput');
      const quantity = qtyInput ? qtyInput.value : '1';

      // Button loading state
      const origHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

      try {
        const json = await window.apiPost('ajax/add_to_cart.php', {
          product_id: productId,
          quantity: quantity
        });

        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (json.success) {
          window.showToast?.(json.message, 'success');

          // Update cart badge counter in header
          const badge = document.getElementById('cartCount');
          if (badge && json.data?.cart_count !== undefined) {
            badge.textContent = json.data.cart_count.toString();
          }

          if (isBuyNow) {
            // Redirect directly to checkout
            window.location.href = 'checkout.php';
          } else {
            // Slide open mini cart on desktop
            if (window.innerWidth > 768 && !window.location.pathname.endsWith('cart.php')) {
              toggleMiniCart(true);
            } else {
              refreshMiniCart();
            }
          }
        } else {
          window.showToast?.(json.message || 'Failed to add item to cart.', 'error');
        }
      } catch (err) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        window.showToast?.('Connection error. Please try again.', 'error');
      }
    });

    // ---------------------------------------------------------------------
    // 4. Cart Page Actions (Quantity Toggles, Deletions, Coupons)
    // ---------------------------------------------------------------------
    // Delegate row-level updates on Cart Page or Mini-Cart list
    document.body.addEventListener('click', async (e) => {
      // Quantity Decrement (-)
      const minus = e.target.closest('.cart-qty-minus');
      if (minus) {
        const input = minus.parentElement.querySelector('.cart-qty-input');
        const productId = minus.dataset.productId;
        let val = parseInt(input.value);

        if (val > 1) {
          val--;
          input.value = val.toString();
          await updateCartQty(productId, val, input);
        }
      }

      // Quantity Increment (+)
      const plus = e.target.closest('.cart-qty-plus');
      if (plus) {
        const input = plus.parentElement.querySelector('.cart-qty-input');
        const productId = plus.dataset.productId;
        const maxVal = parseInt(input.getAttribute('max') || '999');
        let val = parseInt(input.value);

        if (val < maxVal) {
          val++;
          input.value = val.toString();
          await updateCartQty(productId, val, input);
        }
      }

      // Item Delete / Remove Button
      const deleteBtn = e.target.closest('.btn-remove-cart-item');
      if (deleteBtn) {
        e.preventDefault();
        const productId = deleteBtn.dataset.productId;
        if (!productId) return;

        if (confirm('Are you sure you want to remove this item from your cart?')) {
          await removeCartItem(productId, deleteBtn);
        }
      }
    });

    // AJAX helper to update quantity
    async function updateCartQty(productId, qty, inputEl) {
      const originalVal = parseInt(inputEl.dataset.original || qty.toString());
      
      const json = await window.apiPost('ajax/update_cart.php', {
        product_id: productId,
        quantity: qty
      });

      if (json.success) {
        inputEl.dataset.original = qty.toString();
        
        // Recalculate item line total on the cart page
        const row = inputEl.closest('.cart-item-row');
        if (row) {
          const unitPrice = parseFloat(row.dataset.price);
          const lineTotalEl = row.querySelector('.cart-item-line-total');
          if (lineTotalEl) {
            lineTotalEl.textContent = formatCurrency(unitPrice * qty);
          }
        }
        
        refreshMiniCart();
      } else {
        // Revert value in input on stock error
        inputEl.value = originalVal.toString();
        refreshMiniCart();
      }
    }

    // AJAX helper to remove item
    async function removeCartItem(productId, btnEl) {
      const json = await window.apiPost('ajax/remove_cart.php', {
        product_id: productId
      });

      if (json.success) {
        window.showToast?.(json.message, 'success');
        
        // Fade out row if on Cart Page
        const row = btnEl.closest('.cart-item-row');
        if (row) {
          row.style.opacity = '0';
          row.style.transform = 'translateX(-20px)';
          setTimeout(() => {
            row.remove();
            refreshMiniCart();
          }, 300);
        } else {
          refreshMiniCart();
        }
      }
    }

    // ---------------------------------------------------------------------
    // 5. Coupon Application Form
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
          // Reload page to show coupon row and totals, or refresh mini-cart
          window.location.reload();
        } else {
          window.showToast?.(json.message, 'error');
        }
      });
    }

    // ---------------------------------------------------------------------
    // 6. Coupon Removal
    // ---------------------------------------------------------------------
    const removeCouponBtn = document.getElementById('btnRemoveCoupon');
    removeCouponBtn?.addEventListener('click', async (e) => {
      e.preventDefault();
      
      removeCouponBtn.disabled = true;
      removeCouponBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

      const json = await window.apiPost('cart.php', {
        remove_coupon: '1'
      });

      if (json.success) {
        window.showToast?.(json.message, 'success');
        window.location.reload();
      }
    });

  });
})();
