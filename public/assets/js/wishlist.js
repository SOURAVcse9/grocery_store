/**
 * ==========================================================================
 * public/assets/js/wishlist.js
 * ==========================================================================
 * Manage client-side wishlist triggers:
 *   - Toggling wishlist heart buttons (Add/Remove) on product lists
 *   - Removing items from the dedicated wishlist page with animations
 *   - Adding or Moving items to the shopping cart
 *   - Clearing the entire wishlist
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // ---------------------------------------------------------------------
    // 1. Global Wishlist Toggle (Add/Remove Heart clicks)
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-add-to-wishlist, .btn-wishlist');
      if (!btn) return;

      e.preventDefault();
      const productId = btn.dataset.productId;
      if (!productId) return;

      const isAdded = btn.classList.contains('in-wishlist');
      const action = isAdded ? 'remove' : 'add';

      // Disable briefly to prevent spamming
      btn.style.pointerEvents = 'none';

      try {
        const json = await window.apiPost('ajax/wishlist.php', {
          action: action,
          product_id: productId
        });

        btn.style.pointerEvents = '';

        if (json.success) {
          window.showToast?.(json.message, 'success');
          
          // Toggle class & heart icon
          btn.classList.toggle('in-wishlist');
          const icon = btn.querySelector('i');
          if (icon) {
            if (action === 'add') {
              icon.className = 'fas fa-heart';
            } else {
              icon.className = 'far fa-heart';
            }
          }

          // Update header wishlist counter badge
          updateWishlistBadgeCount(json.data?.wishlist_count);
        }
      } catch (err) {
        btn.style.pointerEvents = '';
      }
    });

    function updateWishlistBadgeCount(count) {
      const badge = document.getElementById('wishlistCount');
      if (!badge) {
        // If badge didn't exist, reload header icon area or create element
        const heartLink = document.querySelector('a[href*="wishlist.php"]');
        if (heartLink && count > 0) {
          const newBadge = document.createElement('span');
          newBadge.className = 'icon-badge';
          newBadge.id = 'wishlistCount';
          newBadge.textContent = count.toString();
          heartLink.appendChild(newBadge);
        }
        return;
      }

      if (count > 0) {
        badge.textContent = count.toString();
      } else {
        badge.remove();
      }
    }

    // ---------------------------------------------------------------------
    // 2. Remove Wishlist Item (inside wishlist.php page)
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-wishlist-remove');
      if (!btn) return;

      const productId = btn.dataset.productId;
      if (!productId) return;

      btn.disabled = true;

      const json = await window.apiPost('ajax/wishlist.php', {
        action: 'remove',
        product_id: productId
      });

      if (json.success) {
        window.showToast?.(json.message, 'success');
        updateWishlistBadgeCount(json.data?.wishlist_count);
        
        // Animate row removal
        const card = document.getElementById(`wishlist-item-${productId}`);
        if (card) {
          card.style.transition = 'all 300ms ease';
          card.style.opacity = '0';
          card.style.transform = 'scale(0.9)';
          setTimeout(() => {
            card.remove();
            checkEmptyWishlist();
          }, 300);
        }
      } else {
        btn.disabled = false;
      }
    });

    // ---------------------------------------------------------------------
    // 3. Add to Cart from Wishlist
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-wishlist-cart');
      if (!btn) return;

      const productId = btn.dataset.productId;
      if (!productId) return;

      btn.disabled = true;
      const origHtml = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

      // Call standard cart addition POST
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
    // 4. Move to Cart (Add to Cart + Remove from Wishlist)
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-wishlist-move');
      if (!btn) return;

      const productId = btn.dataset.productId;
      if (!productId) return;

      btn.disabled = true;
      const origHtml = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Moving...';

      // 1. Add to Cart
      const cartJson = await window.apiPost('ajax/add_to_cart.php', {
        product_id: productId,
        quantity: '1'
      });

      if (cartJson.success) {
        // 2. Remove from Wishlist
        const wishlistJson = await window.apiPost('ajax/wishlist.php', {
          action: 'remove',
          product_id: productId
        });

        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (wishlistJson.success) {
          window.showToast?.('Item moved to shopping cart successfully!', 'success');
          updateWishlistBadgeCount(wishlistJson.data?.wishlist_count);
          
          // Update header cart count
          const cartBadge = document.getElementById('cartCount');
          if (cartBadge && cartJson.data?.cart_count !== undefined) {
            cartBadge.textContent = cartJson.data.cart_count.toString();
          }

          // Collapse card row
          const card = document.getElementById(`wishlist-item-${productId}`);
          if (card) {
            card.style.transition = 'all 300ms ease';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.9)';
            setTimeout(() => {
              card.remove();
              checkEmptyWishlist();
            }, 300);
          }
        }
      } else {
        btn.disabled = false;
        btn.innerHTML = origHtml;
      }
    });

    // ---------------------------------------------------------------------
    // 5. Clear Entire Wishlist
    // ---------------------------------------------------------------------
    const btnClear = document.querySelector('.btn-clear-wishlist');
    btnClear?.addEventListener('click', async () => {
      if (confirm('Are you sure you want to delete all saved items from your wishlist?')) {
        btnClear.disabled = true;

        const json = await window.apiPost('ajax/wishlist.php', {
          action: 'clear'
        });

        if (json.success) {
          window.showToast?.(json.message, 'success');
          updateWishlistBadgeCount(0);
          
          // Reload wishlist list HTML container dynamically
          const container = document.getElementById('wishlistItemsContainer');
          if (container) {
            container.innerHTML = `
              <div class="cart-empty-page" style="box-shadow:none; border:none; background:transparent;">
                <div class="cart-empty-icon" style="color:var(--color-text-faint);"><i class="far fa-heart"></i></div>
                <h2>Your Wishlist is Empty</h2>
                <p>Save items you like to buy later. Click the heart icon on any product page!</p>
                <a href="products.php" class="btn btn-primary">Discover Products</a>
              </div>
            `;
          }
          btnClear.remove();
        } else {
          btnClear.disabled = false;
        }
      }
    });

    // Helper checks if list empty to show notice
    async function checkEmptyWishlist() {
      const container = document.getElementById('wishlistItemsContainer');
      const cards = container?.querySelectorAll('.wishlist-item-card');
      if (container && (!cards || cards.length === 0)) {
        container.innerHTML = `
          <div class="cart-empty-page" style="box-shadow:none; border:none; background:transparent;">
            <div class="cart-empty-icon" style="color:var(--color-text-faint);"><i class="far fa-heart"></i></div>
            <h2>Your Wishlist is Empty</h2>
            <p>Save items you like to buy later. Click the heart icon on any product page!</p>
            <a href="products.php" class="btn btn-primary">Discover Products</a>
          </div>
        `;
        document.querySelector('.btn-clear-wishlist')?.remove();
      }
    }

  });
})();
