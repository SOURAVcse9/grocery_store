/**
 * ==========================================================================
 * public/assets/js/checkout.js
 * ==========================================================================
 * Checkout page interactive controls.
 * Manages address toggles, payment methods, and AJAX checkout submissions.
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const checkoutForm = document.getElementById('checkoutForm');
    if (!checkoutForm) return;

    const addressFormBox = document.getElementById('newAddressFormBox');
    const requiredInputs = addressFormBox?.querySelectorAll('input[required]');

    // ---------------------------------------------------------------------
    // 1. Saved Address Selector Toggles
    // ---------------------------------------------------------------------
    const addressRadios = checkoutForm.querySelectorAll('input[name="address_id"]');
    addressRadios.forEach((radio) => {
      radio.addEventListener('change', () => {
        // Highlight active card
        addressRadios.forEach((r) => {
          const card = r.closest('.address-card-option');
          card?.classList.remove('selected');
        });

        const activeCard = radio.closest('.address-card-option');
        activeCard?.classList.add('selected');

        // Toggle New Address form visibility & requirements
        if (radio.value === 'new') {
          if (addressFormBox) {
            addressFormBox.style.display = 'block';
            requiredInputs?.forEach((input) => input.setAttribute('required', 'required'));
          }
        } else {
          if (addressFormBox) {
            addressFormBox.style.display = 'none';
            requiredInputs?.forEach((input) => input.removeAttribute('required'));
          }
        }
      });
    });

    // ---------------------------------------------------------------------
    // 2. Payment Method Card Selection Highlights
    // ---------------------------------------------------------------------
    const paymentRadios = checkoutForm.querySelectorAll('input[name="payment_method"]');
    paymentRadios.forEach((radio) => {
      radio.addEventListener('change', () => {
        paymentRadios.forEach((r) => {
          const card = r.closest('.payment-card');
          card?.classList.remove('selected');
        });

        const activeCard = radio.closest('.payment-card');
        activeCard?.classList.add('selected');
      });
    });

    // ---------------------------------------------------------------------
    // 3. AJAX Form Submission & Order Placement
    // ---------------------------------------------------------------------
    checkoutForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const btnSubmit = document.getElementById('btnPlaceOrder');
      if (!btnSubmit) return;

      const originalHtml = btnSubmit.innerHTML;
      btnSubmit.disabled = true;
      btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Placing Order...';

      const formData = new FormData(checkoutForm);

      try {
        const res = await fetch(checkoutForm.action, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        });

        const json = await res.json();

        if (json.success && json.data?.redirect) {
          window.showToast?.(json.message, 'success');
          // Update cart badge if needed
          const badge = document.getElementById('cartCount');
          if (badge) badge.textContent = '0';
          
          setTimeout(() => {
            window.location.href = json.data.redirect;
          }, 1000);
        } else {
          btnSubmit.disabled = false;
          btnSubmit.innerHTML = originalHtml;
          window.showToast?.(json.message || 'Failed to place order. Please try again.', 'error');
        }
      } catch (err) {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = originalHtml;
        window.showToast?.('Connection error. Please try again.', 'error');
      }
    });

  });
})();
