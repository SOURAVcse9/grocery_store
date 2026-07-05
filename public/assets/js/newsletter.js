/**
 * ==========================================================================
 * public/assets/js/newsletter.js
 * ==========================================================================
 * Handle client-side newsletter sign-up actions:
 *   - Intercept submitting the inline form via AJAX
 *   - Display smooth success confirmations or duplicate tags alerts
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('newsletterForm');
    
    form?.addEventListener('submit', async (e) => {
      e.preventDefault();

      const input = form.querySelector('input[type="email"]');
      const email = input?.value.trim() ?? '';

      if (!email) return;

      const btn = document.getElementById('btnSubscribeNewsletter');
      const origHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing...';

      try {
        const actionUrl = form.getAttribute('action') || 'newsletter.php';
        const json = await window.apiPost(actionUrl, {
          email: email
        });

        if (json.success) {
          window.showToast?.(json.message, 'success');
          
          // Replace form with success notice
          const parent = form.parentNode;
          if (parent) {
            form.remove();
            const successDiv = document.createElement('div');
            successDiv.className = 'newsletter-success-message';
            successDiv.style.marginTop = '16px';
            successDiv.style.fontWeight = '700';
            successDiv.style.color = '#2b8a3e'; // green success
            successDiv.innerHTML = `<i class="fas fa-circle-check" style="margin-right:6px;"></i> ${json.message}`;
            parent.appendChild(successDiv);
          }
        } else {
          btn.disabled = false;
          btn.innerHTML = origHtml;
          window.showToast?.(json.message || 'Subscription failed.', 'error');
        }
      } catch (err) {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        window.showToast?.('Connection error. Please try again.', 'error');
      }
    });

  });
})();
