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
    // F4: Focus Customer Search
    if (e.key === 'F4') {
      e.preventDefault();
      document.getElementById('posCustomerSearch')?.focus();
    }
    // F8: Hold / Suspend Sale
    if (e.key === 'F8') {
      e.preventDefault();
      if (typeof window.suspendPOSCart === 'function') {
        window.suspendPOSCart();
      }
    }
    // F9: Focus split cash input inside modal if visible, otherwise focus sidebar cash input
    if (e.key === 'F9') {
      e.preventDefault();
      const modalCash = document.querySelector('#checkoutPaymentModal #splitCash');
      if (modalCash && modalCash.offsetParent !== null) {
        modalCash.focus();
      } else {
        document.getElementById('splitCash')?.focus();
      }
    }
    // F10: Complete Sale
    if (e.key === 'F10') {
      e.preventDefault();
      const confirmBtn = document.getElementById('btnConfirmPOSSale');
      if (confirmBtn && confirmBtn.offsetParent !== null && !confirmBtn.disabled) {
        confirmBtn.click();
      } else if (typeof window.submitPOSCheckoutFinalist === 'function') {
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
  });

  let activeIndex = -1;
  let currentProducts = [];

  // Customer search context variables
  let custActiveIndex = -1;
  let currentCustomers = [];

  function initPOSHandlers() {
    const searchInput = document.getElementById('posFilterSearch');
    const dropdown = document.getElementById('posAutocompleteDropdown');
    
    const custSearch = document.getElementById('posCustomerSearch');
    const custDropdown = document.getElementById('posCustomerAutocomplete');

    // 1. Product catalog search input handler
    if (searchInput && dropdown) {
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
    }

    // 2. Customer search autocomplete input handler
    if (custSearch && custDropdown) {
      let custTimeout = null;
      custSearch.addEventListener('input', () => {
        clearTimeout(custTimeout);
        const val = custSearch.value.trim();
        if (val.length < 1) {
          custDropdown.innerHTML = '';
          custDropdown.style.display = 'none';
          currentCustomers = [];
          custActiveIndex = -1;
          return;
        }

        custTimeout = setTimeout(() => {
          fetch('ajax/search_customer.php?q=' + encodeURIComponent(val))
            .then(r => r.json())
            .then(data => {
              if (data.success && data.customers) {
                currentCustomers = data.customers;
                custActiveIndex = -1;
                renderCustomerDropdown(custDropdown, currentCustomers);
              }
            });
        }, 150);
      });

      custSearch.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          if (currentCustomers.length === 0) return;
          custActiveIndex = (custActiveIndex + 1) % currentCustomers.length;
          highlightCustomerItem(custDropdown);
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          if (currentCustomers.length === 0) return;
          custActiveIndex = (custActiveIndex - 1 + currentCustomers.length) % currentCustomers.length;
          highlightCustomerItem(custDropdown);
        } else if (e.key === 'Enter') {
          e.preventDefault();
          if (currentCustomers.length > 0 && custActiveIndex >= 0) {
            selectPOSCustomer(currentCustomers[custActiveIndex]);
            custSearch.value = '';
            custDropdown.innerHTML = '';
            custDropdown.style.display = 'none';
            currentCustomers = [];
            custActiveIndex = -1;
          } else if (custSearch.value.trim() !== '') {
            const val = custSearch.value.trim();
            let phoneMatch = currentCustomers.find(c => c.phone === val);
            if (phoneMatch) {
              selectPOSCustomer(phoneMatch);
              custSearch.value = '';
              custDropdown.innerHTML = '';
              custDropdown.style.display = 'none';
              currentCustomers = [];
              custActiveIndex = -1;
            } else {
              if (confirm(`Customer "${val}" not found. Create new customer?`)) {
                document.getElementById('custNewPhone').value = /^\d+$/.test(val) ? val : '';
                document.getElementById('custNewName').value = /^\d+$/.test(val) ? '' : val;
                
                const modalEl = document.getElementById('createCustomerModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                  const modal = new bootstrap.Modal(modalEl);
                  modal.show();
                }
              }
            }
          }
        } else if (e.key === 'Escape') {
          custDropdown.innerHTML = '';
          custDropdown.style.display = 'none';
          currentCustomers = [];
          custActiveIndex = -1;
        }
      });
    }

    // Bind real-time input change listeners for payment splits in the popup modal
    const splitCash = document.getElementById('splitCash');
    const splitCard = document.getElementById('splitCard');
    const splitBkash = document.getElementById('splitBkash');
    const splitWallet = document.getElementById('splitWallet');
    const splitCardTxn = document.getElementById('splitCardTxnId');
    const splitBkashTxn = document.getElementById('splitBkashTxnId');

    if (splitCash) splitCash.addEventListener('input', updateModalChangeDue);
    if (splitCard) splitCard.addEventListener('input', updateModalChangeDue);
    if (splitBkash) splitBkash.addEventListener('input', updateModalChangeDue);
    if (splitWallet) splitWallet.addEventListener('input', updateModalChangeDue);
    if (splitCardTxn) splitCardTxn.addEventListener('input', updateModalChangeDue);
    if (splitBkashTxn) splitBkashTxn.addEventListener('input', updateModalChangeDue);

    // Close all dropdowns on clicking outside
    document.addEventListener('click', (e) => {
      if (searchInput && e.target !== searchInput && e.target !== dropdown) {
        dropdown.innerHTML = '';
        dropdown.style.display = 'none';
        currentProducts = [];
        activeIndex = -1;
      }
      if (custSearch && e.target !== custSearch && e.target !== custDropdown) {
        custDropdown.innerHTML = '';
        custDropdown.style.display = 'none';
        currentCustomers = [];
        custActiveIndex = -1;
      }
    });

    // Handle new customer form submissions
    const frmCreateCust = document.getElementById('frmCreateCustomer');
    if (frmCreateCust) {
      frmCreateCust.addEventListener('submit', (e) => {
        e.preventDefault();
        submitNewCustomer();
      });
    }

    // Handle POS split payment confirmation form submissions
    const frmPOSPay = document.getElementById('frmPOSPayment');
    if (frmPOSPay) {
      frmPOSPay.addEventListener('submit', (e) => {
        e.preventDefault();
        confirmPOSSale();
      });
    }
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
      div.style.cssText = 'padding:8px 12px; cursor:pointer; font-size:12px; border-bottom:1px solid var(--color-border); display:flex; justify-content:space-between; align-items:center; background:#fff;';
      
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

  // Customer Autocomplete Render Helpers
  function renderCustomerDropdown(dropdown, customers) {
    dropdown.innerHTML = '';
    if (customers.length === 0) {
      dropdown.style.display = 'none';
      return;
    }

    customers.forEach((c) => {
      const div = document.createElement('div');
      div.className = 'cust-autocomplete-item';
      div.style.cssText = 'padding:6px 12px; cursor:pointer; font-size:11px; border-bottom:1px solid var(--color-border); display:flex; justify-content:space-between; align-items:center; background:#fff;';
      
      div.innerHTML = `
        <div>
          <strong>${c.full_name}</strong><br>
          <span style="font-size:9px; color:var(--color-text-faint);">Phone: ${c.phone} | Email: ${c.email}</span>
        </div>
        <div style="text-align:right; font-weight:700;">৳${parseFloat(c.wallet_balance).toFixed(2)}</div>
      `;

      div.addEventListener('click', () => {
        selectPOSCustomer(c);
        const searchInput = document.getElementById('posCustomerSearch');
        if (searchInput) searchInput.value = '';
        dropdown.innerHTML = '';
        dropdown.style.display = 'none';
        currentCustomers = [];
        custActiveIndex = -1;
      });

      dropdown.appendChild(div);
    });

    dropdown.style.display = 'block';
  }

  function highlightCustomerItem(dropdown) {
    const items = dropdown.querySelectorAll('.cust-autocomplete-item');
    items.forEach((item, idx) => {
      if (idx === custActiveIndex) {
        item.style.background = '#e6fcf5';
        item.scrollIntoView({ block: 'nearest' });
      } else {
        item.style.background = '#fff';
      }
    });
  }

  function selectPOSCustomer(c) {
    const selectEl = document.getElementById('posCustomerSelect');
    const labelEl = document.getElementById('posCurrentCustomerLabel');
    if (!selectEl) return;

    selectEl.value = c.id;
    selectEl.setAttribute('data-wallet', c.wallet_balance);
    selectEl.setAttribute('data-points', c.reward_points);
    selectEl.setAttribute('data-name', c.full_name);

    if (labelEl) {
      labelEl.innerText = `${c.full_name} (${c.phone})`;
    }

    if (typeof window.updateLoyaltyUI === 'function') {
      window.updateLoyaltyUI();
    }
  }

  function submitNewCustomer() {
    const nameEl = document.getElementById('custNewName');
    const mobileEl = document.getElementById('custNewPhone');
    const emailEl = document.getElementById('custNewEmail');
    const addressEl = document.getElementById('custNewAddress');

    if (!nameEl || !mobileEl) {
      alert('Name and Mobile number input fields are missing.');
      return;
    }

    const name = nameEl.value.trim();
    const mobile = mobileEl.value.trim();
    const email = emailEl ? emailEl.value.trim() : '';
    const address = addressEl ? addressEl.value.trim() : '';

    if (name === '' || mobile === '') {
      alert('Name and Mobile number are required.');
      return;
    }

    const formData = new FormData();
    formData.append('name', name);
    formData.append('mobile', mobile);
    formData.append('email', email);
    formData.append('address', address);
    formData.append('csrf_token', window.csrfToken || '');

    fetch('ajax/create_customer.php', {
      method: 'POST',
      body: formData
    })
      .then(r => r.json())
      .then(data => {
        if (data.success && data.customer) {
          selectPOSCustomer(data.customer);
          
          const frm = document.getElementById('frmCreateCustomer');
          if (frm) frm.reset();
          const modalEl = document.getElementById('createCustomerModal');
          if (modalEl && typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
          }
          alert('Customer created and selected successfully!');
        } else {
          alert('Failed to register customer: ' + data.error);
        }
      })
      .catch(err => {
        console.error(err);
        alert('Communication error while saving customer.');
      });
  }

  // Split Checkout Popup Handlers
  function submitPOSCheckoutFinalist() {
    const keys = Object.keys(window.touchCart || {});
    if (keys.length === 0) {
      alert('POS Cart is empty.');
      return;
    }
    
    let subtotal = 0;
    Object.keys(window.touchCart).forEach(k => {
      subtotal += (window.touchCart[k].price * window.touchCart[k].qty);
    });
    
    const discountEl = document.getElementById('posCartDiscount');
    const discount = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    const vat = Math.max(subtotal - discount, 0) * 0.05;
    const totalPayable = Math.max(subtotal - discount, 0) + vat;
    
    const payableTotalEl = document.getElementById('modalPayableTotal');
    if (payableTotalEl) payableTotalEl.innerText = '৳' + totalPayable.toFixed(2);
    
    // Reset payment fields safely
    const splitCash = document.getElementById('splitCash');
    const splitCard = document.getElementById('splitCard');
    const splitCardTxn = document.getElementById('splitCardTxnId');
    const splitBkash = document.getElementById('splitBkash');
    const splitBkashTxn = document.getElementById('splitBkashTxnId');
    
    if (splitCash) splitCash.value = totalPayable.toFixed(2);
    if (splitCard) splitCard.value = '0';
    if (splitCardTxn) splitCardTxn.value = '';
    if (splitBkash) splitBkash.value = '0';
    if (splitBkashTxn) splitBkashTxn.value = '';
    
    // Sync wallet details
    const selectEl = document.getElementById('posCustomerSelect');
    if (selectEl) {
      const walletBalance = parseFloat(selectEl.getAttribute('data-wallet')) || 0;
      const splitWallet = document.getElementById('splitWallet');
      if (splitWallet) {
        splitWallet.value = '0';
        splitWallet.max = walletBalance.toFixed(2);
      }
    }

    updateModalChangeDue();

    const modalEl = document.getElementById('checkoutPaymentModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  }

  function updateModalChangeDue() {
    let subtotal = 0;
    Object.keys(window.touchCart || {}).forEach(k => {
      subtotal += (window.touchCart[k].price * window.touchCart[k].qty);
    });
    const discountEl = document.getElementById('posCartDiscount');
    const discount = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    const vat = Math.max(subtotal - discount, 0) * 0.05;
    const totalPayable = Math.max(subtotal - discount, 0) + vat;

    const splitCash = document.getElementById('splitCash');
    const splitCard = document.getElementById('splitCard');
    const splitBkash = document.getElementById('splitBkash');
    const splitWallet = document.getElementById('splitWallet');

    const cash = splitCash ? parseFloat(splitCash.value) || 0 : 0;
    const card = splitCard ? parseFloat(splitCard.value) || 0 : 0;
    const bkash = splitBkash ? parseFloat(splitBkash.value) || 0 : 0;
    const wallet = splitWallet ? parseFloat(splitWallet.value) || 0 : 0;

    // Toggle Transaction ID inputs visibility
    const cardTxnRow = document.getElementById('cardTxnRow');
    const bkashTxnRow = document.getElementById('bkashTxnRow');
    if (cardTxnRow) cardTxnRow.style.display = card > 0 ? 'grid' : 'none';
    if (bkashTxnRow) bkashTxnRow.style.display = bkash > 0 ? 'grid' : 'none';

    const nonCashPaid = card + bkash + wallet;
    const totalEntered = cash + nonCashPaid;
    const remainingDue = Math.max(totalPayable - totalEntered, 0);
    const change = Math.max(cash - Math.max(totalPayable - nonCashPaid, 0), 0);

    const totalEnteredEl = document.getElementById('modalTotalEntered');
    const remainingDueEl = document.getElementById('modalRemainingDue');
    const changeDueEl = document.getElementById('modalChangeDue');

    if (totalEnteredEl) totalEnteredEl.innerText = '৳' + totalEntered.toFixed(2);
    if (remainingDueEl) remainingDueEl.innerText = '৳' + remainingDue.toFixed(2);
    if (changeDueEl) changeDueEl.innerText = '৳' + change.toFixed(2);

    // Validate inputs
    let isValid = true;
    
    if (totalEntered < totalPayable) {
      isValid = false;
    }
    const splitCardTxn = document.getElementById('splitCardTxnId');
    if (card > 0 && splitCardTxn && splitCardTxn.value.trim() === '') {
      isValid = false;
    }
    const splitBkashTxn = document.getElementById('splitBkashTxnId');
    if (bkash > 0 && splitBkashTxn && splitBkashTxn.value.trim() === '') {
      isValid = false;
    }
    const selectEl = document.getElementById('posCustomerSelect');
    if (selectEl) {
      const walletMax = parseFloat(selectEl.getAttribute('data-wallet')) || 0;
      if (wallet > walletMax) {
        isValid = false;
      }
    }

    const confirmBtn = document.getElementById('btnConfirmPOSSale');
    if (confirmBtn) confirmBtn.disabled = !isValid;
  }

  function confirmPOSSale() {
    const keys = Object.keys(window.touchCart || {});
    const discountEl = document.getElementById('posCartDiscount');
    const discount = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    
    const splitCash = document.getElementById('splitCash');
    const splitCard = document.getElementById('splitCard');
    const splitCardTxn = document.getElementById('splitCardTxnId');
    const splitBkash = document.getElementById('splitBkash');
    const splitBkashTxn = document.getElementById('splitBkashTxnId');
    const splitWallet = document.getElementById('splitWallet');

    const cash = splitCash ? parseFloat(splitCash.value) || 0 : 0;
    const card = splitCard ? parseFloat(splitCard.value) || 0 : 0;
    const cardTxnId = splitCardTxn ? splitCardTxn.value.trim() : '';
    const bkash = splitBkash ? parseFloat(splitBkash.value) || 0 : 0;
    const bkashTxnId = splitBkashTxn ? splitBkashTxn.value.trim() : '';
    const wallet = splitWallet ? parseFloat(splitWallet.value) || 0 : 0;
    
    const selectEl = document.getElementById('posCustomerSelect');
    const customerId = selectEl ? selectEl.value : '0';
    const itemsData = keys.map(k => window.touchCart[k]);
    
    // Assemble transaction details for storage in note
    let paymentNote = 'POS checkout.';
    if (card > 0) paymentNote += ` Card: ৳${card} (Txn: ${cardTxnId}).`;
    if (bkash > 0) paymentNote += ` Mobile Banking: ৳${bkash} (Txn: ${bkashTxnId}).`;
    if (wallet > 0) paymentNote += ` Wallet: ৳${wallet}.`;
    if (cash > 0) paymentNote += ` Cash: ৳${cash}.`;

    const formData = new FormData();
    formData.append('items', JSON.stringify(itemsData));
    formData.append('discount', discount.toString());
    formData.append('cash', cash.toString());
    formData.append('card', card.toString());
    formData.append('card_txn', cardTxnId);
    formData.append('bkash', bkash.toString());
    formData.append('bkash_txn', bkashTxnId);
    formData.append('wallet', wallet.toString());
    formData.append('customer_id', customerId);
    formData.append('note', paymentNote);
    formData.append('csrf_token', window.csrfToken || '');
    
    fetch('checkout.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Checkout finalized successfully!');
            // Print receipt
            window.open('receipts.php?id=' + data.order_id, '_blank', 'width=400,height=600');
            
            // Clear cart
            window.touchCart = {};
            if (typeof window.renderTouchCart === 'function') {
                window.renderTouchCart();
            }

            // Hide modal
            const modalEl = document.getElementById('checkoutPaymentModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
            
            if (selectEl) {
              const walkin = selectEl.getAttribute('data-name') || '';
              if (!walkin.includes('Walk-in')) {
                  location.reload();
              }
            }
        } else {
            alert('POS Checkout failed: ' + data.error);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Server communications error during checkout.');
    });
  }

  // Export handlers to global window scope
  window.submitPOSCheckoutFinalist = submitPOSCheckoutFinalist;
  window.checkoutProcess = submitPOSCheckoutFinalist;
  window.confirmPOSSale = confirmPOSSale;
  window.updateModalChangeDue = updateModalChangeDue;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPOSHandlers);
  } else {
    initPOSHandlers();
  }
})();
