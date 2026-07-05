/**
 * ==========================================================================
 * public/assets/js/account.js
 * ==========================================================================
 * Customer account dashboard scripting.
 * Manages profile photo instant previewing and address forms validation.
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // ---------------------------------------------------------------------
    // 1. Profile Avatar Instant Preview
    // ---------------------------------------------------------------------
    const avatarInput = document.getElementById('avatarUploadInput');
    const avatarPreview = document.getElementById('avatarPreviewImage');

    if (avatarInput && avatarPreview) {
      avatarInput.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        // Check file size (max 2MB)
        if (file.size > 2 * 1024 * 1024) {
          window.showToast?.('Image size must be smaller than 2MB.', 'error');
          avatarInput.value = ''; // Reset input
          return;
        }

        // Validate image type
        if (!file.type.match('image.*')) {
          window.showToast?.('Please select a valid image file (JPEG, PNG, WEBP).', 'error');
          avatarInput.value = '';
          return;
        }

        // Instant Preview using FileReader
        const reader = new FileReader();
        reader.onload = (event) => {
          if (event.target?.result) {
            avatarPreview.src = event.target.result.toString();
          }
        };
        reader.readAsDataURL(file);
      });
    }

    // ---------------------------------------------------------------------
    // 2. Client-side Form Validation for Addresses
    // ---------------------------------------------------------------------
    const addressForm = document.getElementById('addressForm');
    if (addressForm) {
      addressForm.addEventListener('submit', (e) => {
        const recipientName = document.getElementById('recipient_name')?.value.trim();
        const phone = document.getElementById('phone')?.value.trim();
        const addressLine1 = document.getElementById('address_line1')?.value.trim();
        const city = document.getElementById('city')?.value.trim();

        let errors = [];

        if (!recipientName || recipientName.length < 2) {
          errors.push('Recipient name must be at least 2 characters.');
        }

        // Bangladeshi phone length check
        if (!phone || !/^(?:\+8801|8801|01)[3-9]\d{8}$/.test(phone)) {
          errors.push('Please enter a valid Bangladeshi phone number.');
        }

        if (!addressLine1 || addressLine1.length < 5) {
          errors.push('Address Line 1 must be at least 5 characters.');
        }

        if (!city || city.length < 2) {
          errors.push('City/District is required.');
        }

        if (errors.length > 0) {
          e.preventDefault();
          window.showToast?.(errors[0], 'error');
        }
      });
    }

  });
})();
