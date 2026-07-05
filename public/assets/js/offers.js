/**
 * ==========================================================================
 * public/assets/js/offers.js
 * ==========================================================================
 * Handle client-side promotional coupon copying:
 *   - Write coupon codes to clipboard via navigator.clipboard
 *   - Change button styling to reflect successful copy actions
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    
    // Copy Coupon Code Trigger
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-copy-coupon');
      if (!btn) return;

      const code = btn.dataset.code;
      if (!code) return;

      try {
        await navigator.clipboard.writeText(code);
        
        // Success feedback
        window.showToast?.('Coupon code "' + code + '" copied to clipboard!', 'success');
        
        btn.classList.add('copied');
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.disabled = true;

        setTimeout(() => {
          btn.classList.remove('copied');
          btn.innerHTML = origHtml;
          btn.disabled = false;
        }, 2000);

      } catch (err) {
        window.showToast?.('Failed to copy coupon code.', 'error');
      }
    });

  });
})();
