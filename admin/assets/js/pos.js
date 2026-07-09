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
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
      return;
    }
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
      // Don't intercept ESC if a Bootstrap modal is open
      const anyOpenModal = document.querySelector('.modal.show');
      if (anyOpenModal) return;
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
              if (data.success) {
                currentCustomers = data.customers || [];
                custActiveIndex = -1;

                // Auto-select on exact phone match — no dropdown needed
                const exactPhone = currentCustomers.find(c => c.phone === val);
                if (exactPhone) {
                  selectPOSCustomer(exactPhone);
                  custSearch.value = '';
                  custDropdown.innerHTML = '';
                  custDropdown.style.display = 'none';
                  currentCustomers = [];
                  return;
                }

                renderCustomerDropdown(custDropdown, currentCustomers, val);
              }
            })
            .catch(() => {
              renderCustomerDropdown(custDropdown, [], val);
            });
        }, 250);
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
          } else if (currentCustomers.length === 1) {
            selectPOSCustomer(currentCustomers[0]);
            custSearch.value = '';
            custDropdown.innerHTML = '';
            custDropdown.style.display = 'none';
            currentCustomers = [];
            custActiveIndex = -1;
          } else if (currentCustomers.length === 0) {
            const val = custSearch.value.trim();
            if (val !== '') {
              custSearch.value = '';
              custDropdown.innerHTML = '';
              custDropdown.style.display = 'none';
              currentCustomers = [];
              custActiveIndex = -1;

              const phoneEl = document.getElementById('custNewPhone');
              const nameEl = document.getElementById('custNewName');
              if (phoneEl) phoneEl.value = /^\d+$/.test(val) ? val : '';
              if (nameEl) nameEl.value = /^\d+$/.test(val) ? '' : val;

              const modalEl = document.getElementById('createCustomerModal');
              if (modalEl && typeof bootstrap !== 'undefined') {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
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

    // Focus barcode search input initially
    if (searchInput) {
      searchInput.focus();
    }

    // Bind real-time input change listeners for payment splits in the popup modal
    const inputsToBind = [
      'splitCash', 'splitCard', 'splitCardNo', 'splitCardRef', 'splitCardBank',
      'splitBkash', 'splitMobileProvider', 'splitBkashTxnId', 'splitWallet'
    ];
    inputsToBind.forEach(id => {
      const el = document.getElementById(id);
      if (el) {
        el.addEventListener('input', updateModalChangeDue);
        el.addEventListener('change', updateModalChangeDue);
      }
    });

    // Autofocus barcode search input when modals are closed
    const custModalEl = document.getElementById('createCustomerModal');
    if (custModalEl) {
      custModalEl.addEventListener('hidden.bs.modal', () => {
        document.getElementById('posFilterSearch')?.focus();
      });
    }
    const payModalEl = document.getElementById('checkoutPaymentModal');
    if (payModalEl) {
      payModalEl.addEventListener('hidden.bs.modal', () => {
        document.getElementById('posFilterSearch')?.focus();
      });
    }

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
  function renderCustomerDropdown(dropdown, customers, searchVal) {
    dropdown.innerHTML = '';

    if (customers.length === 0) {
      // Show "not found → create" row
      const notFound = document.createElement('div');
      notFound.style.cssText = 'padding:10px 14px; cursor:pointer; font-size:11px; display:flex; justify-content:space-between; align-items:center; background:#fff3f3; color:#c0392b;';
      notFound.innerHTML = `
        <span><i class="fas fa-user-plus" style="margin-right:6px;"></i>No customer found. <strong>Create new customer?</strong></span>
        <span style="font-size:9px; border:1px solid #c0392b; padding:2px 6px; border-radius:10px;">+ New</span>
      `;
      notFound.addEventListener('click', () => {
        dropdown.innerHTML = '';
        dropdown.style.display = 'none';
        currentCustomers = [];

        const custSearch = document.getElementById('posCustomerSearch');
        const phoneEl = document.getElementById('custNewPhone');
        const nameEl = document.getElementById('custNewName');
        const val = custSearch ? custSearch.value.trim() : (searchVal || '');
        if (phoneEl) phoneEl.value = /^\d+$/.test(val) ? val : '';
        if (nameEl) nameEl.value = /^\d+$/.test(val) ? '' : val;

        const modalEl = document.getElementById('createCustomerModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
          const modal = new bootstrap.Modal(modalEl);
          modal.show();
        }
      });
      dropdown.appendChild(notFound);
      dropdown.style.display = 'block';
      return;
    }

    customers.forEach((c) => {
      const div = document.createElement('div');
      div.className = 'cust-autocomplete-item';
      div.style.cssText = 'padding:7px 12px; cursor:pointer; font-size:11px; border-bottom:1px solid var(--color-border); display:flex; justify-content:space-between; align-items:center; background:#fff;';
      
      div.innerHTML = `
        <div>
          <strong>${c.full_name}</strong><br>
          <span style="font-size:9px; color:var(--color-text-faint);">📱 ${c.phone} &nbsp;|&nbsp; 💰 ৳${parseFloat(c.wallet_balance).toFixed(2)}</span>
        </div>
        <div style="text-align:right; font-size:9px; color:var(--color-primary); font-weight:700;">Select ✓</div>
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
    const genderEl = document.getElementById('custNewGender');
    const birthdayEl = document.getElementById('custNewBirthday');

    if (!nameEl || !mobileEl) {
      alert('Name and Mobile number input fields are missing.');
      return;
    }

    const name = nameEl.value.trim();
    const mobile = mobileEl.value.trim();
    const email = emailEl ? emailEl.value.trim() : '';
    const address = addressEl ? addressEl.value.trim() : '';
    const gender = genderEl ? genderEl.value.trim() : '';
    const birthday = birthdayEl ? birthdayEl.value.trim() : '';

    if (name === '' || mobile === '') {
      alert('Name and Mobile number are required.');
      return;
    }

    const formData = new FormData();
    formData.append('name', name);
    formData.append('mobile', mobile);
    formData.append('email', email);
    formData.append('address', address);
    formData.append('gender', gender);
    formData.append('birthday', birthday);
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
          // Brief non-blocking success toast
          const label = document.getElementById('posCurrentCustomerLabel');
          if (label) {
            label.style.color = '#2b9348';
            label.innerText = `\u2713 ${data.customer.full_name} selected`;
            setTimeout(() => {
              label.style.color = 'var(--color-primary)';
            }, 2000);
          }
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
    const couponEl = document.getElementById('posCartCoupon');
    const discount = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    const coupon = couponEl ? parseFloat(couponEl.value) || 0 : 0;
    
    const totalDiscounts = discount + coupon;
    const taxableAmount = Math.max(subtotal - totalDiscounts, 0);
    const vat = taxableAmount * 0.05;
    const totalPayable = taxableAmount + vat;
    
    // Set breakdown text elements inside modal
    const modalSubtotal = document.getElementById('modalSubtotal');
    const modalVat = document.getElementById('modalVat');
    const modalDiscount = document.getElementById('modalDiscount');
    const modalCoupon = document.getElementById('modalCoupon');
    const modalPayableTotal = document.getElementById('modalPayableTotal');
    
    if (modalSubtotal) modalSubtotal.innerText = '৳' + subtotal.toFixed(2);
    if (modalVat) modalVat.innerText = '৳' + vat.toFixed(2);
    if (modalDiscount) modalDiscount.innerText = '৳' + discount.toFixed(2);
    if (modalCoupon) modalCoupon.innerText = '৳' + coupon.toFixed(2);
    if (modalPayableTotal) modalPayableTotal.innerText = '৳' + totalPayable.toFixed(2);
    
    // Reset payment fields safely
    const splitCash = document.getElementById('splitCash');
    const splitCard = document.getElementById('splitCard');
    const splitCardNo = document.getElementById('splitCardNo');
    const splitCardRef = document.getElementById('splitCardRef');
    const splitCardBank = document.getElementById('splitCardBank');
    
    const splitBkash = document.getElementById('splitBkash');
    const splitMobileProvider = document.getElementById('splitMobileProvider');
    const splitBkashTxnId = document.getElementById('splitBkashTxnId');
    const splitWallet = document.getElementById('splitWallet');
    
    if (splitCash) splitCash.value = totalPayable.toFixed(2);
    if (splitCard) splitCard.value = '0';
    if (splitCardNo) splitCardNo.value = '';
    if (splitCardRef) splitCardRef.value = '';
    if (splitCardBank) splitCardBank.value = '';
    
    if (splitBkash) splitBkash.value = '0';
    if (splitMobileProvider) splitMobileProvider.value = 'bKash';
    if (splitBkashTxnId) splitBkashTxnId.value = '';
    
    // Sync wallet details
    const selectEl = document.getElementById('posCustomerSelect');
    if (selectEl) {
      const walletBalance = parseFloat(selectEl.getAttribute('data-wallet')) || 0;
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
    const couponEl = document.getElementById('posCartCoupon');
    const discount = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    const coupon = couponEl ? parseFloat(couponEl.value) || 0 : 0;
    
    const totalDiscounts = discount + coupon;
    const taxableAmount = Math.max(subtotal - totalDiscounts, 0);
    const vat = taxableAmount * 0.05;
    const totalPayable = taxableAmount + vat;

    const splitCash = document.getElementById('splitCash');
    const splitCard = document.getElementById('splitCard');
    const splitBkash = document.getElementById('splitBkash');
    const splitWallet = document.getElementById('splitWallet');

    const cash = splitCash ? parseFloat(splitCash.value) || 0 : 0;
    const card = splitCard ? parseFloat(splitCard.value) || 0 : 0;
    const bkash = splitBkash ? parseFloat(splitBkash.value) || 0 : 0;
    const wallet = splitWallet ? parseFloat(splitWallet.value) || 0 : 0;

    // Toggle Details fields visibility
    const cardDetailsRow = document.getElementById('cardDetailsRow');
    const mobileDetailsRow = document.getElementById('mobileDetailsRow');
    if (cardDetailsRow) cardDetailsRow.style.display = card > 0 ? 'flex' : 'none';
    if (mobileDetailsRow) mobileDetailsRow.style.display = bkash > 0 ? 'flex' : 'none';

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
    
    if (totalEntered < totalPayable - 0.01) {
      isValid = false;
    }
    
    // Card field validations
    if (card > 0) {
      const splitCardRef = document.getElementById('splitCardRef');
      const splitCardBank = document.getElementById('splitCardBank');
      if (!splitCardRef || splitCardRef.value.trim() === '') isValid = false;
      if (!splitCardBank || splitCardBank.value.trim() === '') isValid = false;
    }
    
    // Mobile banking validations
    if (bkash > 0) {
      const splitBkashTxn = document.getElementById('splitBkashTxnId');
      if (!splitBkashTxn || splitBkashTxn.value.trim() === '') isValid = false;
    }
    
    // Wallet credit validation
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
    const couponEl = document.getElementById('posCartCoupon');
    const discount = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    const coupon = couponEl ? parseFloat(couponEl.value) || 0 : 0;
    const totalDiscounts = discount + coupon;
    
    const splitCash = document.getElementById('splitCash');
    const splitCard = document.getElementById('splitCard');
    const splitCardNo = document.getElementById('splitCardNo');
    const splitCardRef = document.getElementById('splitCardRef');
    const splitCardBank = document.getElementById('splitCardBank');
    
    const splitBkash = document.getElementById('splitBkash');
    const splitMobileProvider = document.getElementById('splitMobileProvider');
    const splitBkashTxnId = document.getElementById('splitBkashTxnId');
    const splitWallet = document.getElementById('splitWallet');

    const cash = splitCash ? parseFloat(splitCash.value) || 0 : 0;
    const card = splitCard ? parseFloat(splitCard.value) || 0 : 0;
    const cardNo = splitCardNo ? splitCardNo.value.trim() : '';
    const cardRef = splitCardRef ? splitCardRef.value.trim() : '';
    const cardBank = splitCardBank ? splitCardBank.value.trim() : '';
    
    const bkash = splitBkash ? parseFloat(splitBkash.value) || 0 : 0;
    const mobileProvider = splitMobileProvider ? splitMobileProvider.value : 'bKash';
    const bkashTxnId = splitBkashTxnId ? splitBkashTxnId.value.trim() : '';
    const wallet = splitWallet ? parseFloat(splitWallet.value) || 0 : 0;
    
    const selectEl = document.getElementById('posCustomerSelect');
    const customerId = selectEl ? selectEl.value : '0';
    const itemsData = keys.map(k => window.touchCart[k]);
    
    // Assemble transaction details for storage in note
    let paymentNote = 'POS checkout.';
    if (card > 0) {
      paymentNote += ` Card: ৳${card.toFixed(2)} (Bank: ${cardBank}, Ref: ${cardRef}, CardNo: ${cardNo}).`;
    }
    if (bkash > 0) {
      paymentNote += ` Mobile Banking (${mobileProvider}): ৳${bkash.toFixed(2)} (Txn: ${bkashTxnId}).`;
    }
    if (wallet > 0) {
      paymentNote += ` Wallet: ৳${wallet.toFixed(2)}.`;
    }
    if (cash > 0) {
      paymentNote += ` Cash: ৳${cash.toFixed(2)}.`;
    }

    const formData = new FormData();
    formData.append('items', JSON.stringify(itemsData));
    formData.append('discount', totalDiscounts.toString());
    formData.append('cash', cash.toString());
    formData.append('card', card.toString());
    formData.append('bkash', bkash.toString());
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
            
            // Reset customer selection programmatically
            if (selectEl) {
              const defaultId = selectEl.getAttribute('data-default-id') || '0';
              const defaultName = selectEl.getAttribute('data-default-name') || 'Walk-in Customer';
              const defaultWallet = selectEl.getAttribute('data-default-wallet') || '0.00';
              const defaultPoints = selectEl.getAttribute('data-default-points') || '0';

              selectEl.value = defaultId;
              selectEl.setAttribute('data-wallet', defaultWallet);
              selectEl.setAttribute('data-points', defaultPoints);
              selectEl.setAttribute('data-name', defaultName);
            }

            const labelEl = document.getElementById('posCurrentCustomerLabel');
            if (labelEl) {
              labelEl.innerText = 'Walk-in Customer';
            }

            const custSearch = document.getElementById('posCustomerSearch');
            if (custSearch) {
              custSearch.value = '';
            }

            const loyaltyWidget = document.getElementById('loyaltyWidget');
            if (loyaltyWidget) {
              loyaltyWidget.style.display = 'none';
            }

            // Reset discount and coupon fields
            if (discountEl) discountEl.value = '0';
            if (couponEl) couponEl.value = '0';
            
            // Focus barcode input input field
            const searchInput = document.getElementById('posFilterSearch');
            if (searchInput) {
              searchInput.value = '';
              searchInput.focus();
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
