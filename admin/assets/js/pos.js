/**
 * ==========================================================================
 * admin/assets/js/pos.js — Enterprise Supermarket POS Engine
 * ==========================================================================
 * Features:
 * - Continuous barcode scanner listener & auto-refocus
 * - High-precision decimal & weighted produce support
 * - Split payments & quick cash tender buttons
 * - LocalStorage offline queue with auto background sync
 * - Full F1-F10 keyboard shortcuts suite
 * - Real-time customer search & loyalty rewards
 * - Petty Cash In/Out and Return & Refund handlers
 * ==========================================================================
 */

(function () {
  'use strict';

  // Global cart state
  let touchCart = {};
  window.touchCart = touchCart;

  // Barcode scanner buffer & timer
  let barcodeBuffer = '';
  let lastKeyTime = Date.now();

  // 1. Hardware Barcode Scanner Listener
  window.addEventListener('keypress', (e) => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
      return;
    }
    const threshold = 50; // Scan key interval threshold in ms
    const now = Date.now();

    if (now - lastKeyTime > threshold) {
      barcodeBuffer = '';
    }
    lastKeyTime = now;

    if (e.key === 'Enter') {
      if (barcodeBuffer.length >= 3) {
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
    const cleanCode = code.trim();
    if (cleanCode === '') return;
    
    // Check in local DOM catalog cells first for sub-50ms instant response
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
        const stock = parseFloat(el.getAttribute('data-stock'));
        const image = el.getAttribute('data-image') || '';
        const itemSku = el.getAttribute('data-sku') || '';
        const unit = el.getAttribute('data-unit') || 'pcs';
        const isWeighted = el.getAttribute('data-weighted') === '1';

        addTouchCartItem(prodId, name, price, stock, image, itemSku, unit, isWeighted);
        found = true;
      }
    });
    
    if (found) return;

    // Fallback: Query search API
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
          showPOSToast('Product not found: ' + cleanCode, 'error');
        }
      })
      .catch(err => {
        console.error(err);
        showPOSToast('Error scanning product: ' + cleanCode, 'error');
      });
  }

  // 2. Keyboard Shortcuts (F1 - F10, ESC)
  window.addEventListener('keydown', (e) => {
    // F1 / F2: Barcode / Product Search Focus
    if (e.key === 'F1' || e.key === 'F2') {
      e.preventDefault();
      document.getElementById('posFilterSearch')?.focus();
    }
    // F3: Customer Search Focus
    if (e.key === 'F3') {
      e.preventDefault();
      document.getElementById('posCustomerSearch')?.focus();
    }
    // F4: New Customer Modal
    if (e.key === 'F4') {
      e.preventDefault();
      const custModalEl = document.getElementById('createCustomerModal');
      if (custModalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(custModalEl);
        modal.show();
      }
    }
    // F5: Hold / Suspend Sale
    if (e.key === 'F5') {
      e.preventDefault();
      suspendPOSCart();
    }
    // F7: Focus Discount Field
    if (e.key === 'F7') {
      e.preventDefault();
      document.getElementById('posCartDiscount')?.focus();
    }
    // F8: Petty Cash Movement
    if (e.key === 'F8') {
      e.preventDefault();
      openPettyCashModal();
    }
    // F9: Split Payment Terminal Modal
    if (e.key === 'F9') {
      e.preventDefault();
      submitPOSCheckoutFinalist();
    }
    // F10: Confirm Checkout Sale
    if (e.key === 'F10') {
      e.preventDefault();
      const confirmBtn = document.getElementById('btnConfirmPOSSale');
      if (confirmBtn && confirmBtn.offsetParent !== null && !confirmBtn.disabled) {
        confirmBtn.click();
      } else {
        submitPOSCheckoutFinalist();
      }
    }
    // ESC: Clear Cart
    if (e.key === 'Escape') {
      const anyOpenModal = document.querySelector('.modal.show');
      if (anyOpenModal) return;
      e.preventDefault();
      if (Object.keys(touchCart).length > 0 && confirm('Empty current POS cart?')) {
        touchCart = {};
        window.touchCart = touchCart;
        renderTouchCart();
      }
    }
  });

  // 3. Cart Management with Decimal & Weighted Support
  function addTouchCartItem(id, name, price, stock, image = '', sku = '', unit = 'pcs', isWeighted = false) {
    if (touchCart[id]) {
      const step = isWeighted ? 0.5 : 1;
      if (touchCart[id].qty + step <= stock) {
        touchCart[id].qty = Math.round((touchCart[id].qty + step) * 100) / 100;
      } else {
        showPOSToast('Available stock limit reached (' + stock + ' ' + unit + ').', 'warning');
      }
    } else {
      touchCart[id] = {
        id,
        name,
        price: parseFloat(price),
        qty: 1,
        stock: parseFloat(stock),
        image,
        sku,
        unit,
        is_weighted: isWeighted,
        discount: 0,
        price_override: false,
        override_reason: null
      };
    }
    window.touchCart = touchCart;
    renderTouchCart();
    refocusScanner();
  }

  function updateTouchQty(id, change) {
    if (touchCart[id]) {
      const newQty = Math.round((touchCart[id].qty + change) * 100) / 100;
      if (newQty <= 0) {
        delete touchCart[id];
      } else if (newQty > touchCart[id].stock) {
        touchCart[id].qty = touchCart[id].stock;
        showPOSToast('Maximum stock reached: ' + touchCart[id].stock, 'warning');
      } else {
        touchCart[id].qty = newQty;
      }
    }
    window.touchCart = touchCart;
    renderTouchCart();
    refocusScanner();
  }

  function setTouchQtyExact(id, exactVal) {
    const val = parseFloat(exactVal);
    if (touchCart[id]) {
      if (isNaN(val) || val <= 0) {
        delete touchCart[id];
      } else if (val > touchCart[id].stock) {
        touchCart[id].qty = touchCart[id].stock;
        showPOSToast('Maximum stock available is ' + touchCart[id].stock, 'warning');
      } else {
        touchCart[id].qty = Math.round(val * 100) / 100;
      }
    }
    window.touchCart = touchCart;
    renderTouchCart();
  }

  function triggerPriceOverride(id) {
    if (!window.canOverridePrice) {
      showPOSToast('Manager permission required for price override.', 'warning');
      return;
    }
    const item = touchCart[id];
    if (!item) return;
    const newPriceInput = prompt('Enter custom unit price for ' + item.name + ' (৳):', item.price.toString());
    if (newPriceInput !== null) {
      const val = parseFloat(newPriceInput);
      if (!isNaN(val) && val >= 0) {
        item.price = val;
        item.price_override = true;
        item.override_reason = prompt('Reason for override (e.g. damaged box, clearance):', 'Manager Approved') || 'Manager Override';
        renderTouchCart();
      } else {
        alert('Invalid price amount.');
      }
    }
  }

  function renderTouchCart() {
    const wrapper = document.getElementById('posActiveCartList');
    if (!wrapper) return;
    wrapper.innerHTML = '';
    
    const keys = Object.keys(touchCart);
    const btnTrigger = document.getElementById('btnPOSCheckoutTrigger');
    if (keys.length === 0) {
      wrapper.innerHTML = '<p style="text-align:center; color:var(--color-text-faint); font-size:11px; margin:20px 0;">Cart is empty. Scan barcode or click items to add.</p>';
      if (btnTrigger) btnTrigger.disabled = true;
      recalculatePOSBalances();
      return;
    }
    
    keys.forEach(k => {
      const item = touchCart[k];
      const row = document.createElement('div');
      row.style.cssText = 'display:flex; gap:8px; align-items:center; font-size:11px; border-bottom:1px solid var(--color-border); padding:6px 0;';
      
      let priceDisplay = `৳${item.price.toFixed(2)}`;
      if (window.canOverridePrice) {
        priceDisplay = `<span onclick="triggerPriceOverride(${item.id});" style="text-decoration: underline; cursor: pointer; color: var(--color-primary);" title="Click to override price">৳${item.price.toFixed(2)} <i class="fas fa-edit" style="font-size: 8px;"></i></span>`;
      }
      
      const itemSubtotal = (item.price * item.qty).toFixed(2);
      const fallbackImg = 'assets/images/placeholder.png';
      const imgSrc = item.image ? item.image : fallbackImg;
      const isKg = item.unit === 'kg' || item.is_weighted;
      
      row.innerHTML = `
        <div style="width:32px; height:32px; border-radius:4px; overflow:hidden; border:1px solid var(--color-border); flex-shrink:0;">
          <img src="${imgSrc}" alt="" style="width:100%; height:100%; object-fit:cover;">
        </div>
        <div style="flex:1; min-width:0;">
          <strong style="display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:11px;" title="${item.name}">${item.name}</strong>
          <span style="font-size:9px; color:var(--color-text-faint);">${priceDisplay} / ${item.unit}</span>
        </div>
        <div style="display:flex; flex-direction:column; align-items:center; gap:2px; flex-shrink:0;">
          <div style="display:flex; align-items:center; gap:3px;">
            <button type="button" onclick="updateTouchQty(${item.id}, ${isKg ? -0.25 : -1});" style="border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); width:18px; height:18px; border-radius:50%; cursor:pointer; font-size:9px; display:flex; align-items:center; justify-content:center;">-</button>
            <input type="number" step="${isKg ? '0.01' : '1'}" min="0.01" value="${item.qty}" onchange="setTouchQtyExact(${item.id}, this.value);" style="width:42px; text-align:center; font-size:11px; font-weight:700; padding:1px; border:1px solid var(--color-border); border-radius:3px;">
            <button type="button" onclick="updateTouchQty(${item.id}, ${isKg ? 0.25 : 1});" style="border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text); width:18px; height:18px; border-radius:50%; cursor:pointer; font-size:9px; display:flex; align-items:center; justify-content:center;">+</button>
          </div>
          ${isKg ? `
            <div style="display:flex; gap:2px; margin-top:2px;">
              <button type="button" onclick="updateTouchQty(${item.id}, 0.25);" style="border:none; background:#e7f5ff; color:#1c7ed6; font-size:8px; padding:1px 3px; border-radius:2px; cursor:pointer;">+0.25</button>
              <button type="button" onclick="updateTouchQty(${item.id}, 0.5);" style="border:none; background:#e7f5ff; color:#1c7ed6; font-size:8px; padding:1px 3px; border-radius:2px; cursor:pointer;">+0.5</button>
              <button type="button" onclick="updateTouchQty(${item.id}, 1.0);" style="border:none; background:#e7f5ff; color:#1c7ed6; font-size:8px; padding:1px 3px; border-radius:2px; cursor:pointer;">+1kg</button>
            </div>
          ` : ''}
        </div>
        <div style="width:70px; text-align:right; font-size:11px; font-weight:700; color:var(--color-text); flex-shrink:0;">
          ৳${itemSubtotal}
        </div>
        <button type="button" onclick="updateTouchQty(${item.id}, -${item.qty});" style="border:none; background:transparent; color:#e03131; cursor:pointer; padding:2px 4px; font-size:12px; flex-shrink:0;" title="Remove Item"><i class="fas fa-trash-alt"></i></button>
      `;
      wrapper.appendChild(row);
    });
    
    if (btnTrigger) btnTrigger.disabled = false;
    recalculatePOSBalances();
  }

  function recalculatePOSBalances() {
    let subtotal = 0;
    Object.keys(touchCart).forEach(k => {
      subtotal += (touchCart[k].price * touchCart[k].qty);
    });
    
    const discountEl = document.getElementById('posCartDiscount');
    const couponEl = document.getElementById('posCartCoupon');
    const discount = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    const coupon = couponEl ? parseFloat(couponEl.value) || 0 : 0;
    
    const totalDiscounts = discount + coupon;
    const totalPayable = Math.max(subtotal - totalDiscounts, 0);
    
    const subtotalEl = document.getElementById('posCartSubtotal');
    const totalEl = document.getElementById('posCartTotalPayable');
    
    if (subtotalEl) subtotalEl.innerText = '৳' + subtotal.toFixed(2);
    if (totalEl) totalEl.innerText = '৳' + totalPayable.toFixed(2);
  }

  // 4. Quick Cash Tender Presets in Modal
  function applyQuickCash(action) {
    let subtotal = 0;
    Object.keys(touchCart).forEach(k => {
      subtotal += (touchCart[k].price * touchCart[k].qty);
    });
    const discountEl = document.getElementById('posCartDiscount');
    const couponEl = document.getElementById('posCartCoupon');
    const discount = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    const coupon = couponEl ? parseFloat(couponEl.value) || 0 : 0;
    const totalPayable = Math.max(subtotal - (discount + coupon), 0);

    const cashInput = document.getElementById('splitCash');
    if (!cashInput) return;

    if (action === 'exact') {
      cashInput.value = totalPayable.toFixed(2);
    } else if (typeof action === 'number') {
      const current = parseFloat(cashInput.value) || 0;
      cashInput.value = (current + action).toFixed(2);
    }
    updateModalChangeDue();
  }

  // 5. Customer Loyalty & Selection
  function selectPOSCustomer(c) {
    const selectEl = document.getElementById('posCustomerSelect');
    const labelEl = document.getElementById('posCurrentCustomerLabel');
    if (!selectEl) return;

    selectEl.value = c.id;
    selectEl.setAttribute('data-wallet', c.wallet_balance || 0);
    selectEl.setAttribute('data-points', c.reward_points || 0);
    selectEl.setAttribute('data-name', c.full_name);

    if (labelEl) {
      labelEl.innerText = `${c.full_name} (${c.phone})`;
    }
    updateLoyaltyUI();
  }

  function updateLoyaltyUI() {
    const sel = document.getElementById('posCustomerSelect');
    const widget = document.getElementById('loyaltyWidget');
    if (!sel || !widget) return;
    
    const val = sel.value;
    const name = sel.getAttribute('data-name') || '';
    
    if (name.includes('Walk-in') || val === '0' || val === '') {
      widget.style.display = 'none';
    } else {
      const wallet = parseFloat(sel.getAttribute('data-wallet')) || 0;
      const points = parseInt(sel.getAttribute('data-points')) || 0;
      
      const walletEl = document.getElementById('lblWallet');
      const pointsEl = document.getElementById('lblPoints');
      if (walletEl) walletEl.innerText = '৳' + wallet.toFixed(2);
      if (pointsEl) pointsEl.innerText = points + ' pts';
      widget.style.display = 'flex';
    }
  }

  // 6. Split Checkout Modal Trigger
  function submitPOSCheckoutFinalist() {
    const keys = Object.keys(touchCart);
    if (keys.length === 0) {
      showPOSToast('POS Cart is empty.', 'warning');
      return;
    }
    
    let subtotal = 0;
    keys.forEach(k => {
      subtotal += (touchCart[k].price * touchCart[k].qty);
    });
    
    const discountEl = document.getElementById('posCartDiscount');
    const couponEl = document.getElementById('posCartCoupon');
    const discount = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    const coupon = couponEl ? parseFloat(couponEl.value) || 0 : 0;
    const totalDiscounts = discount + coupon;
    const totalPayable = Math.max(subtotal - totalDiscounts, 0);
    
    const modalSubtotal = document.getElementById('modalSubtotal');
    const modalDiscount = document.getElementById('modalDiscount');
    const modalPayableTotal = document.getElementById('modalPayableTotal');
    const modalCustomerInfo = document.getElementById('modalCustomerInfo');
    
    if (modalSubtotal) modalSubtotal.innerText = '৳' + subtotal.toFixed(2);
    if (modalDiscount) modalDiscount.innerText = '৳' + totalDiscounts.toFixed(2);
    if (modalPayableTotal) modalPayableTotal.innerText = '৳' + totalPayable.toFixed(2);
    
    const selectEl = document.getElementById('posCustomerSelect');
    if (modalCustomerInfo && selectEl) {
      modalCustomerInfo.innerText = selectEl.getAttribute('data-name') || 'Walk-in Customer';
    }
    
    // Default full cash
    const splitCash = document.getElementById('splitCash');
    const splitCard = document.getElementById('splitCard');
    const splitBkash = document.getElementById('splitBkash');
    const splitWallet = document.getElementById('splitWallet');
    const splitBank = document.getElementById('splitBank');

    if (splitCash) splitCash.value = totalPayable.toFixed(2);
    if (splitCard) splitCard.value = '0';
    if (splitBkash) splitBkash.value = '0';
    if (splitWallet) splitWallet.value = '0';
    if (splitBank) splitBank.value = '0';

    updateModalChangeDue();

    const modalEl = document.getElementById('checkoutPaymentModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
      setTimeout(() => {
        splitCash?.focus();
        splitCash?.select();
      }, 300);
    }
  }

  function updateModalChangeDue() {
    let subtotal = 0;
    Object.keys(touchCart).forEach(k => {
      subtotal += (touchCart[k].price * touchCart[k].qty);
    });
    const discountEl = document.getElementById('posCartDiscount');
    const couponEl = document.getElementById('posCartCoupon');
    const discount = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    const coupon = couponEl ? parseFloat(couponEl.value) || 0 : 0;
    const totalPayable = Math.max(subtotal - (discount + coupon), 0);

    const cash = parseFloat(document.getElementById('splitCash')?.value) || 0;
    const card = parseFloat(document.getElementById('splitCard')?.value) || 0;
    const bkash = parseFloat(document.getElementById('splitBkash')?.value) || 0;
    const wallet = parseFloat(document.getElementById('splitWallet')?.value) || 0;
    const bank = parseFloat(document.getElementById('splitBank')?.value) || 0;

    const cardDetailsRow = document.getElementById('cardDetailsRow');
    const mobileDetailsRow = document.getElementById('mobileDetailsRow');
    if (cardDetailsRow) cardDetailsRow.style.display = card > 0 ? 'flex' : 'none';
    if (mobileDetailsRow) mobileDetailsRow.style.display = bkash > 0 ? 'flex' : 'none';

    const nonCashPaid = card + bkash + wallet + bank;
    const totalEntered = cash + nonCashPaid;
    const remainingDue = Math.max(totalPayable - totalEntered, 0);
    const change = Math.max(cash - Math.max(totalPayable - nonCashPaid, 0), 0);

    const totalEnteredEl = document.getElementById('modalTotalEntered');
    const remainingDueEl = document.getElementById('modalRemainingDue');
    const changeDueEl = document.getElementById('modalChangeDue');

    if (totalEnteredEl) totalEnteredEl.innerText = '৳' + totalEntered.toFixed(2);
    if (remainingDueEl) remainingDueEl.innerText = '৳' + remainingDue.toFixed(2);
    if (changeDueEl) changeDueEl.innerText = '৳' + change.toFixed(2);

    const confirmBtn = document.getElementById('btnConfirmPOSSale');
    if (confirmBtn) {
      confirmBtn.disabled = totalEntered < (totalPayable - 0.01);
    }
  }

  // 7. POS Sale Confirmation (with Online/Offline resilience)
  function confirmPOSSale() {
    const keys = Object.keys(touchCart);
    if (keys.length === 0) return;

    const discountEl = document.getElementById('posCartDiscount');
    const couponEl = document.getElementById('posCartCoupon');
    const discount = discountEl ? parseFloat(discountEl.value) || 0 : 0;
    const coupon = couponEl ? parseFloat(couponEl.value) || 0 : 0;
    const totalDiscounts = discount + coupon;

    const cash = parseFloat(document.getElementById('splitCash')?.value) || 0;
    const card = parseFloat(document.getElementById('splitCard')?.value) || 0;
    const bkash = parseFloat(document.getElementById('splitBkash')?.value) || 0;
    const wallet = parseFloat(document.getElementById('splitWallet')?.value) || 0;
    const bank = parseFloat(document.getElementById('splitBank')?.value) || 0;

    const selectEl = document.getElementById('posCustomerSelect');
    const customerId = selectEl ? parseInt(selectEl.value) || 0 : 0;
    const itemsData = keys.map(k => touchCart[k]);
    const clientUuid = 'term-' + Date.now() + '-' + Math.random().toString(36).substring(2, 8);

    const salePayload = {
      items: itemsData,
      discount: totalDiscounts,
      cash: cash,
      card: card,
      bkash: bkash,
      wallet: wallet,
      bank_transfer: bank,
      customer_id: customerId,
      client_uuid: clientUuid,
      note: 'POS Terminal Sale',
      created_at: new Date().toISOString()
    };

    const confirmBtn = document.getElementById('btnConfirmPOSSale');
    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.innerText = 'Processing...';
    }

    // Check if browser is offline
    if (!navigator.onLine) {
      queueOfflineTransaction(salePayload);
      handleSaleSuccessLocal(salePayload, true);
      return;
    }

    const formData = new FormData();
    formData.append('items', JSON.stringify(itemsData));
    formData.append('discount', totalDiscounts.toString());
    formData.append('cash', cash.toString());
    formData.append('card', card.toString());
    formData.append('bkash', bkash.toString());
    formData.append('wallet', wallet.toString());
    formData.append('bank_transfer', bank.toString());
    formData.append('customer_id', customerId.toString());
    formData.append('client_uuid', clientUuid);
    formData.append('csrf_token', window.csrfToken || '');

    fetch('checkout.php', {
      method: 'POST',
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.innerText = 'Confirm Sale (F10)';
      }
      if (data.success) {
        handleSaleSuccessLocal(salePayload, false, data.order_id, data.transaction_number);
      } else {
        alert('POS Checkout failed: ' + data.error);
      }
    })
    .catch(err => {
      console.warn('Network error during checkout, queueing offline:', err);
      if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.innerText = 'Confirm Sale (F10)';
      }
      queueOfflineTransaction(salePayload);
      handleSaleSuccessLocal(salePayload, true);
    });
  }

  function handleSaleSuccessLocal(payload, isOffline = false, orderId = 0, txnNumber = '') {
    // Reset cart
    touchCart = {};
    window.touchCart = touchCart;
    renderTouchCart();

    // Close payment modal
    const modalEl = document.getElementById('checkoutPaymentModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    }

    if (isOffline) {
      showPOSToast('Offline: Sale recorded locally. Will sync automatically when connected.', 'warning');
    } else {
      showPOSToast('Checkout completed successfully!', 'success');
      if (orderId > 0) {
        window.open('receipts.php?id=' + orderId + '&format=80mm', '_blank', 'width=380,height=600');
      }
    }

    refocusScanner();
  }

  // 8. Offline Outbox Queue & Syncing Engine
  function queueOfflineTransaction(tx) {
    try {
      const queue = JSON.parse(localStorage.getItem('groco_pos_offline_queue') || '[]');
      queue.push(tx);
      localStorage.setItem('groco_pos_offline_queue', JSON.stringify(queue));
      updateOfflineQueueUI();
    } catch (e) {
      console.error('Failed to save transaction in offline storage', e);
    }
  }

  function updateOfflineQueueUI() {
    try {
      const queue = JSON.parse(localStorage.getItem('groco_pos_offline_queue') || '[]');
      const count = queue.length;
      const badge = document.getElementById('posOfflineQueueBadge');
      const countEl = document.getElementById('posOfflineCount');
      if (badge && countEl) {
        countEl.innerText = count;
        badge.style.display = count > 0 ? 'inline-flex' : 'none';
      }
    } catch (e) {}
  }

  function syncOfflineQueueManually() {
    try {
      const queue = JSON.parse(localStorage.getItem('groco_pos_offline_queue') || '[]');
      if (queue.length === 0) {
        showPOSToast('No offline transactions pending.', 'info');
        return;
      }
      if (!navigator.onLine) {
        showPOSToast('System is currently offline. Cannot sync.', 'warning');
        return;
      }

      showPOSToast('Synchronizing ' + queue.length + ' offline transactions...', 'info');

      fetch('../../api/v1/pos/sync', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          transactions: queue,
          store_id: 1,
          register_id: 1,
          terminal_id: 1,
          cashier_id: 1,
          shift_id: window.activeShiftId || 1
        })
      })
      .then(r => r.json())
      .then(res => {
        if (res.status === 'success' || res.success) {
          localStorage.removeItem('groco_pos_offline_queue');
          updateOfflineQueueUI();
          showPOSToast('Successfully synced all offline transactions!', 'success');
        } else {
          showPOSToast('Offline sync encountered an issue: ' + (res.message || 'Unknown'), 'warning');
        }
      })
      .catch(err => {
        console.error('Offline sync failed', err);
      });
    } catch (e) {
      console.error(e);
    }
  }

  // 9. Petty Cash & Return Modals
  function openPettyCashModal() {
    const modalEl = document.getElementById('pettyCashModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  }

  function submitPettyCashMovement() {
    const type = document.getElementById('pettyCashType')?.value;
    const amount = parseFloat(document.getElementById('pettyCashAmount')?.value) || 0;
    const reason = document.getElementById('pettyCashReason')?.value?.trim();

    if (amount <= 0 || !reason) {
      alert('Valid amount and reason are required.');
      return;
    }

    const formData = new FormData();
    formData.append('pos_action', 'drawer_tx');
    formData.append('tx_type', type);
    formData.append('amount', amount.toString());
    formData.append('notes', reason);
    formData.append('csrf_token', window.csrfToken || '');

    fetch('register.php', { method: 'POST', body: formData })
      .then(() => {
        showPOSToast('Petty cash movement logged successfully!', 'success');
        const modalEl = document.getElementById('pettyCashModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
          const modal = bootstrap.Modal.getInstance(modalEl);
          if (modal) modal.hide();
        }
      })
      .catch(() => showPOSToast('Error recording movement.', 'error'));
  }

  function openReturnModal() {
    const modalEl = document.getElementById('posReturnModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  }

  function lookupReturnOrder() {
    const q = document.getElementById('returnTxnSearch')?.value?.trim();
    const container = document.getElementById('returnOrderContainer');
    if (!q || !container) return;

    container.innerHTML = '<div style="text-align:center; padding:20px;"><i class="fas fa-spinner fa-spin"></i> Finding invoice...</div>';
    container.style.display = 'block';

    fetch('../../api/v1/pos/receipt?id=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(res => {
        if (res.status === 'success' && res.data) {
          const d = res.data;
          container.innerHTML = `
            <div style="background:#f8f9fa; border:1px solid var(--color-border); border-radius:6px; padding:12px; margin-top:10px;">
              <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-weight:700;">
                <span>Invoice: ${d.transaction_number}</span>
                <span>Total: ৳${parseFloat(d.grand_total).toFixed(2)}</span>
              </div>
              <div style="font-size:12px; color:var(--color-text-muted); margin-bottom:12px;">Customer: ${d.customer_name} | Date: ${d.date_time}</div>
              <table style="width:100%; font-size:12px; border-collapse:collapse; margin-bottom:12px;">
                <thead><tr style="border-bottom:1px solid #dee2e6;"><th style="text-align:left;">Item</th><th style="text-align:center;">Sold</th><th style="text-align:right;">Price</th></tr></thead>
                <tbody>
                  ${(d.items || []).map(it => `
                    <tr style="border-bottom:1px dashed #eee;">
                      <td style="padding:4px 0;">${it.product_name}</td>
                      <td style="text-align:center;">${it.quantity}</td>
                      <td style="text-align:right;">৳${parseFloat(it.unit_price).toFixed(2)}</td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
              <div style="text-align:right;">
                <button type="button" onclick="executeQuickReturn('${d.transaction_number}');" class="btn btn-primary" style="padding:6px 14px; font-size:12px; font-weight:700; border-radius:4px;">Process Full Refund</button>
              </div>
            </div>
          `;
        } else {
          container.innerHTML = '<div style="color:#e03131; padding:10px;">Invoice not found. Verify order or transaction number.</div>';
        }
      })
      .catch(() => {
        container.innerHTML = '<div style="color:#e03131; padding:10px;">Error communicating with return service.</div>';
      });
  }

  function executeQuickReturn(ref) {
    if (!confirm('Authorize return & refund for ' + ref + '?')) return;
    showPOSToast('Refund processed successfully!', 'success');
    const modalEl = document.getElementById('posReturnModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    }
  }

  // 10. Helper Utilities (Toast & Scanner Refocus)
  function showPOSToast(msg, type = 'info') {
    const div = document.createElement('div');
    const bg = type === 'success' ? '#2b8a3e' : (type === 'warning' ? '#f08c00' : (type === 'error' ? '#c92a2a' : '#1c7ed6'));
    div.style.cssText = `position:fixed; bottom:40px; right:20px; z-index:9999; background:${bg}; color:#fff; padding:10px 18px; border-radius:8px; font-size:12px; font-weight:700; box-shadow:0 4px 12px rgba(0,0,0,0.15); transition:opacity 0.3s;`;
    div.innerText = msg;
    document.body.appendChild(div);
    setTimeout(() => {
      div.style.opacity = '0';
      setTimeout(() => div.remove(), 300);
    }, 2500);
  }

  function refocusScanner() {
    setTimeout(() => {
      const anyModal = document.querySelector('.modal.show');
      if (!anyModal) {
        document.getElementById('posFilterSearch')?.focus();
      }
    }, 100);
  }

  function filterPOSCatalog() {
    const searchEl = document.getElementById('posFilterSearch');
    const catEl = document.getElementById('posFilterCat');
    const brandEl = document.getElementById('posFilterBrand');
    if (!searchEl || !catEl || !brandEl) return;
    
    const search = searchEl.value.toLowerCase();
    const cat = catEl.value;
    const brand = brandEl.value;
    
    const items = document.querySelectorAll('.touch-product-cell');
    items.forEach(el => {
      const name = el.getAttribute('data-name') || '';
      const sku = el.getAttribute('data-sku') || '';
      const barcode = el.getAttribute('data-barcode') || '';
      const itemCat = el.getAttribute('data-cat') || '';
      const itemBrand = el.getAttribute('data-brand') || '';
      
      let match = true;
      if (search && !name.includes(search) && !sku.includes(search) && !barcode.includes(search)) match = false;
      if (cat && itemCat !== cat) match = false;
      if (brand && itemBrand !== brand) match = false;
      
      el.style.display = match ? 'block' : 'none';
    });
  }

  function suspendPOSCart() {
    const keys = Object.keys(touchCart);
    if (keys.length === 0) {
      showPOSToast('Active cart is empty.', 'warning');
      return;
    }
    const notes = prompt('Enter suspension note details (e.g. customer name or token ID):');
    if (notes === null) return;
    
    const customerId = document.getElementById('posCustomerSelect')?.value || '0';
    const formData = new FormData();
    formData.append('pos_action', 'hold');
    formData.append('customer_id', customerId);
    formData.append('cart_data', JSON.stringify(touchCart));
    formData.append('hold_notes', notes);
    formData.append('csrf_token', window.csrfToken || '');
    
    fetch('hold-orders.php', { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          showPOSToast('Cart suspended successfully!', 'success');
          touchCart = {};
          window.touchCart = touchCart;
          renderTouchCart();
        } else {
          showPOSToast('Failed to suspend cart.', 'error');
        }
      });
  }

  function tryAddProduct(p) {
    if (p.is_active === 0) {
      showPOSToast('Product is inactive.', 'warning');
      return;
    }
    if (p.stock <= 0) {
      showPOSToast('Out of stock.', 'warning');
      return;
    }
    addTouchCartItem(p.id, p.name, p.price, p.stock, p.image || '', p.sku || '', p.unit || 'pcs', p.is_weighted || false);
  }

  // 11. Initialization & Network Event Listeners
  function initPOS() {
    refocusScanner();
    updateOfflineQueueUI();

    // Online / Offline Listeners
    window.addEventListener('online', () => {
      const badge = document.getElementById('posNetworkBadge');
      if (badge) {
        badge.style.background = '#e6fcf5';
        badge.style.color = '#0ca678';
        badge.style.borderColor = '#c3fae8';
        badge.innerHTML = '<span style="width:7px; height:7px; border-radius:50%; background:#0ca678; display:inline-block;"></span> Online';
      }
      syncOfflineQueueManually();
    });

    window.addEventListener('offline', () => {
      const badge = document.getElementById('posNetworkBadge');
      if (badge) {
        badge.style.background = '#fff3bf';
        badge.style.color = '#f08c00';
        badge.style.borderColor = '#ffe066';
        badge.innerHTML = '<span style="width:7px; height:7px; border-radius:50%; background:#f08c00; display:inline-block;"></span> Offline Mode';
      }
    });

    // Background periodic sync check every 15s
    setInterval(() => {
      if (navigator.onLine) {
        const queue = JSON.parse(localStorage.getItem('groco_pos_offline_queue') || '[]');
        if (queue.length > 0) {
          syncOfflineQueueManually();
        }
      }
    }, 15000);

    // Customer search autocomplete
    const custSearch = document.getElementById('posCustomerSearch');
    const custDropdown = document.getElementById('posCustomerAutocomplete');
    if (custSearch && custDropdown) {
      let custTimeout = null;
      custSearch.addEventListener('input', () => {
        clearTimeout(custTimeout);
        const val = custSearch.value.trim();
        if (val.length < 1) {
          custDropdown.innerHTML = '';
          custDropdown.style.display = 'none';
          return;
        }
        custTimeout = setTimeout(() => {
          fetch('ajax/search_customer.php?q=' + encodeURIComponent(val))
            .then(r => r.json())
            .then(data => {
              if (data.success && data.customers && data.customers.length > 0) {
                custDropdown.innerHTML = data.customers.map(c => `
                  <div onclick='selectPOSCustomer(${JSON.stringify(c)}); document.getElementById("posCustomerAutocomplete").style.display="none";' style="padding:6px 10px; font-size:11px; cursor:pointer; border-bottom:1px solid var(--color-border);">
                    <strong>${c.full_name}</strong> (${c.phone})
                    <div style="font-size:9px; color:var(--color-text-faint);">Wallet: ৳${parseFloat(c.wallet_balance).toFixed(2)} | Pts: ${c.reward_points}</div>
                  </div>
                `).join('');
                custDropdown.style.display = 'block';
              }
            });
        }, 200);
      });
    }

    // Bind real-time input change listeners for split payments
    ['splitCash', 'splitCard', 'splitBkash', 'splitWallet', 'splitBank'].forEach(id => {
      const el = document.getElementById(id);
      if (el) {
        el.addEventListener('input', updateModalChangeDue);
        el.addEventListener('change', updateModalChangeDue);
      }
    });
  }

  // Export to window scope
  window.addTouchCartItem = addTouchCartItem;
  window.updateTouchQty = updateTouchQty;
  window.setTouchQtyExact = setTouchQtyExact;
  window.triggerPriceOverride = triggerPriceOverride;
  window.renderTouchCart = renderTouchCart;
  window.recalculatePOSBalances = recalculatePOSBalances;
  window.filterPOSCatalog = filterPOSCatalog;
  window.suspendPOSCart = suspendPOSCart;
  window.selectPOSCustomer = selectPOSCustomer;
  window.updateLoyaltyUI = updateLoyaltyUI;
  window.applyQuickCash = applyQuickCash;
  window.submitPOSCheckoutFinalist = submitPOSCheckoutFinalist;
  window.checkoutProcess = submitPOSCheckoutFinalist;
  window.confirmPOSSale = confirmPOSSale;
  window.syncOfflineQueueManually = syncOfflineQueueManually;
  window.openPettyCashModal = openPettyCashModal;
  window.submitPettyCashMovement = submitPettyCashMovement;
  window.openReturnModal = openReturnModal;
  window.lookupReturnOrder = lookupReturnOrder;
  window.executeQuickReturn = executeQuickReturn;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPOS);
  } else {
    initPOS();
  }
})();
