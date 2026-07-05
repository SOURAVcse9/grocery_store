/**
 * ==========================================================================
 * public/assets/js/security.js
 * ==========================================================================
 * Implement client-side protection routines:
 *   - Form Submit Button Debouncing (prevents double/multiple submissions)
 *   - Registration password strength requirements validation
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {

    // ---------------------------------------------------------------------
    // 1. Submit Button Debouncer
    // ---------------------------------------------------------------------
    const forms = document.querySelectorAll('form');
    forms.forEach((form) => {
      form.addEventListener('submit', () => {
        const btn = form.querySelector('button[type="submit"]');
        if (btn && !btn.classList.contains('no-debounce')) {
          // Disable button immediately to prevent double POST clicks
          setTimeout(() => {
            btn.disabled = true;
          }, 10);
        }
      });
    });

    // ---------------------------------------------------------------------
    // 2. Client password strength validator (Registration form)
    // ---------------------------------------------------------------------
    const regPassword = document.getElementById('registerPassword');
    const strengthIndicator = document.getElementById('passwordStrengthIndicator');

    regPassword?.addEventListener('input', () => {
      const val = regPassword.value;
      if (!val) {
        if (strengthIndicator) {
          strengthIndicator.textContent = '';
          strengthIndicator.style.color = '';
        }
        return;
      }

      let score = 0;
      if (val.length >= 8) score++;
      if (/[a-zA-Z]/.test(val)) score++;
      if (/[0-9]/.test(val)) score++;
      if (/[^a-zA-Z0-9]/.test(val)) score++; // special character

      let status = 'Weak';
      let color = '#fa5252'; // red

      if (score === 3) {
        status = 'Medium';
        color = '#e67e22'; // orange
      } else if (score >= 4) {
        status = 'Strong';
        color = '#2b8a3e'; // green
      }

      if (strengthIndicator) {
        strengthIndicator.textContent = 'Password Strength: ' + status;
        strengthIndicator.style.color = color;
        strengthIndicator.style.fontWeight = '700';
        strengthIndicator.style.fontSize = '11px';
      }
    });

  });
})();
