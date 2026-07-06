/**
 * ==========================================================================
 * admin/assets/js/pos.js — POS Cart Calculations & Barcode Scans helper
 * ==========================================================================
 */

(function () {
  'use strict';

  // Listen for barcode scanner keystrokes (scanners simulate fast keyboard inputs ending with Enter)
  let barcodeBuffer = '';
  let lastKeyTime = Date.now();

  window.addEventListener('keypress', (e) => {
    const threshold = 50; // Scan key interval threshold in milliseconds
    const now = Date.now();

    if (now - lastKeyTime > threshold) {
      barcodeBuffer = ''; // Reset if slow typing
    }

    lastKeyTime = now;

    if (e.key === 'Enter') {
      if (barcodeBuffer.length >= 4) {
        // Trigger product scan lookup
        lookupPOSBarcode(barcodeBuffer);
        barcodeBuffer = '';
        e.preventDefault();
      }
    } else {
      if (e.key !== 'Shift') {
        barcodeBuffer += e.key;
      }
    }
  });

  function lookupPOSBarcode(code) {
    console.log('Barcode scanned: ', code);
    const cleanCode = code.trim();
    if (cleanCode === '') return;
    
    // Find item with data-sku or data-barcode matching code in local DOM first
    const cells = document.querySelectorAll('.touch-product-cell');
    let found = false;
    
    cells.forEach(el => {
      const sku = (el.getAttribute('data-sku') || '').toLowerCase();
      const barcode = (el.getAttribute('data-barcode') || '').toLowerCase();
      const id = (el.getAttribute('data-id') || '');
      const lowerCode = cleanCode.toLowerCase();
      
      if (sku === lowerCode || barcode === lowerCode || id === lowerCode) {
        const prodId = parseInt(el.getAttribute('data-id'));
        const name = el.getAttribute('data-name-original') || el.getAttribute('data-name');
        const price = parseFloat(el.getAttribute('data-price'));
        const stock = parseInt(el.getAttribute('data-stock'));
        if (typeof window.addTouchCartItem === 'function') {
          window.addTouchCartItem(prodId, name, price, stock);
          found = true;
        }
      }
    });
    
    if (found) return;

    // Fallback: Query unified AJAX search endpoint
    fetch('ajax/search_products.php?q=' + encodeURIComponent(cleanCode))
      .then(r => r.json())
      .then(data => {
        if (data.success && data.products && data.products.length > 0) {
          // If there is an exact match on barcode or sku, add it directly to cart
          let exactMatch = data.products.find(p => 
            (p.barcode && p.barcode.toLowerCase() === cleanCode.toLowerCase()) || 
            (p.sku && p.sku.toLowerCase() === cleanCode.toLowerCase())
          );
          
          let targetProduct = exactMatch || data.products[0];
          if (targetProduct) {
            tryAddProduct(targetProduct);
          }
        } else {
          alert('Product not found: ' + cleanCode);
        }
      })
      .catch(err => {
        console.error(err);
        alert('Error searching for product: ' + cleanCode);
      });
  }

  // Keyboard shortcut bounds
  window.addEventListener('keydown', (e) => {
    // F2: Focus Search
    if (e.key === 'F2') {
      e.preventDefault();
      document.getElementById('posFilterSearch')?.focus();
    }
    // F4: Focus Customer Select
    if (e.key === 'F4') {
      e.preventDefault();
      document.getElementById('posCustomerSelect')?.focus();
    }
    // F8: Hold / Suspend Sale
    if (e.key === 'F8') {
      e.preventDefault();
      if (typeof window.suspendPOSCart === 'function') {
        window.suspendPOSCart();
      }
    }
    // F9: Focus split cash input
    if (e.key === 'F9') {
      e.preventDefault();
      document.getElementById('splitCash')?.focus();
    }
    // F10: Complete Sale
    if (e.key === 'F10') {
      e.preventDefault();
      if (typeof window.submitPOSCheckoutFinalist === 'function') {
        window.submitPOSCheckoutFinalist();
      }
    }
    // ESC: Cancel Sale / Reset Cart
    if (e.key === 'Escape') {
      e.preventDefault();
      if (confirm('Are you sure you want to cancel the current sale and empty the cart?')) {
        if (typeof window.touchCart !== 'undefined') {
          window.touchCart = {};
          if (typeof window.renderTouchCart === 'function') {
            window.renderTouchCart();
          }
        }
      }
    }

    // Ctrl + S: Save/Suspend Bill (Fallback compatibility)
    if (e.ctrlKey && e.key.toLowerCase() === 's') {
      e.preventDefault();
      if (typeof window.suspendPOSCart === 'function') {
        window.suspendPOSCart();
      }
    }
    // Ctrl + P: Complete Payment / Checkout (Fallback compatibility)
    if (e.ctrlKey && e.key.toLowerCase() === 'p') {
      e.preventDefault();
      if (typeof window.submitPOSCheckoutFinalist === 'function') {
        window.submitPOSCheckoutFinalist();
      }
    }
  });

  let activeIndex = -1;
  let currentProducts = [];

  function initSearchEnter() {
    const searchInput = document.getElementById('posFilterSearch');
    const dropdown = document.getElementById('posAutocompleteDropdown');
    if (!searchInput || !dropdown) return;

    // Handle typing / search query
    let timeout = null;
    searchInput.addEventListener('input', () => {
      clearTimeout(timeout);
      const val = searchInput.value.trim();
      if (val.length < 1) {
        dropdown.innerHTML = '';
        dropdown.style.display = 'none';
        currentProducts = [];
        activeIndex = -1;
        return;
      }

      timeout = setTimeout(() => {
        fetch('ajax/search_products.php?q=' + encodeURIComponent(val))
          .then(r => r.json())
          .then(data => {
            if (data.success && data.products) {
              currentProducts = data.products;
              activeIndex = -1;
              
              // If only ONE exact barcode/sku match is returned, add to cart instantly
              if (currentProducts.length === 1) {
                const p = currentProducts[0];
                const cleanInput = val.toLowerCase();
                const barcodeMatch = (p.barcode && p.barcode.toLowerCase() === cleanInput);
                const skuMatch = (p.sku && p.sku.toLowerCase() === cleanInput);
                
                if (barcodeMatch || skuMatch) {
                  tryAddProduct(p);
                  searchInput.value = '';
                  dropdown.innerHTML = '';
                  dropdown.style.display = 'none';
                  currentProducts = [];
                  return;
                }
              }

              renderDropdown(dropdown, currentProducts);
            }
          });
      }, 150);
    });

    // Keyboard navigation (Up, Down, Enter, Escape)
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (currentProducts.length === 0) return;
        activeIndex = (activeIndex + 1) % currentProducts.length;
        highlightItem(dropdown);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (currentProducts.length === 0) return;
        activeIndex = (activeIndex - 1 + currentProducts.length) % currentProducts.length;
        highlightItem(dropdown);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (currentProducts.length > 0 && activeIndex >= 0) {
          const p = currentProducts[activeIndex];
          tryAddProduct(p);
          searchInput.value = '';
          dropdown.innerHTML = '';
          dropdown.style.display = 'none';
          currentProducts = [];
          activeIndex = -1;
        } else if (searchInput.value.trim() !== '') {
          lookupPOSBarcode(searchInput.value.trim());
          searchInput.value = '';
          dropdown.innerHTML = '';
          dropdown.style.display = 'none';
          currentProducts = [];
        }
      } else if (e.key === 'Escape') {
        dropdown.innerHTML = '';
        dropdown.style.display = 'none';
        currentProducts = [];
        activeIndex = -1;
      }
    });

    // Close dropdown on click outside
    document.addEventListener('click', (e) => {
      if (e.target !== searchInput && e.target !== dropdown) {
        dropdown.innerHTML = '';
        dropdown.style.display = 'none';
        currentProducts = [];
        activeIndex = -1;
      }
    });
  }

  function tryAddProduct(p) {
    if (p.is_active === 0) {
      alert('Product is inactive.');
      return;
    }
    if (p.stock <= 0) {
      alert('Out of Stock.');
      return;
    }
    if (typeof window.addTouchCartItem === 'function') {
      window.addTouchCartItem(p.id, p.name, p.price, p.stock);
    }
  }

  function renderDropdown(dropdown, products) {
    dropdown.innerHTML = '';
    if (products.length === 0) {
      dropdown.style.display = 'none';
      return;
    }

    products.forEach((p, idx) => {
      const div = document.createElement('div');
      div.className = 'autocomplete-item';
      div.style.cssText = 'padding:8px 12px; cursor:pointer; font-size:12px; border-bottom:1px solid var(--color-border); display:flex; justify-content:space-between; align-items:center;';
      
      let statusStr = '';
      let styleColor = 'var(--color-text)';
      if (p.is_active === 0) {
        statusStr = ' <span style="color:#e03131; font-weight:700;">(Product is inactive.)</span>';
        styleColor = '#adb5bd';
      } else if (p.stock <= 0) {
        statusStr = ' <span style="color:#f59f00; font-weight:700;">(Out of Stock.)</span>';
        styleColor = '#adb5bd';
      }

      div.innerHTML = `
        <div>
          <strong style="color:${styleColor};">${p.name}</strong>${statusStr}<br>
          <span style="font-size:10px; color:var(--color-text-faint);">SKU: ${p.sku} | Barcode: ${p.barcode}</span>
        </div>
        <div style="font-weight:700; color:var(--color-primary);">৳${p.price.toFixed(2)}</div>
      `;

      div.addEventListener('click', () => {
        tryAddProduct(p);
        const searchInput = document.getElementById('posFilterSearch');
        if (searchInput) searchInput.value = '';
        dropdown.innerHTML = '';
        dropdown.style.display = 'none';
        currentProducts = [];
        activeIndex = -1;
      });

      dropdown.appendChild(div);
    });

    dropdown.style.display = 'block';
  }

  function highlightItem(dropdown) {
    const items = dropdown.querySelectorAll('.autocomplete-item');
    items.forEach((item, idx) => {
      if (idx === activeIndex) {
        item.style.background = '#e6fcf5';
        item.scrollIntoView({ block: 'nearest' });
      } else {
        item.style.background = '#fff';
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSearchEnter);
  } else {
    initSearchEnter();
  }
})();
