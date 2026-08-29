/**
 * ==========================================================================
 * public/assets/js/reviews.js
 * ==========================================================================
 * Handle client-side product review interactions securely.
 * ==========================================================================
 */

(function () {
  'use strict';

  function initReviews() {
    // ---------------------------------------------------------------------
    // 1. Star Rating Input Selector in Form
    // ---------------------------------------------------------------------
    const starContainer = document.getElementById('formRatingSelectStars');
    const stars = starContainer ? starContainer.querySelectorAll('.rating-select-star') : null;
    const ratingInput = document.getElementById('formRatingInput');
    const commentInput = document.getElementById('reviewComment');
    const titleInput = document.getElementById('reviewTitle');
    const submitBtn = document.getElementById('btnSubmitReview');
    const helpText = document.querySelector('.field-help-text');

    function updateFormValidity() {
      if (!submitBtn) return;
      const rating = parseInt(ratingInput?.value || '0', 10);
      const commentLen = commentInput?.value.trim().length || 0;
      
      if (helpText && commentInput) {
        if (commentLen === 0) {
          helpText.textContent = 'Review must be between 10 and 1000 characters.';
          helpText.style.color = 'var(--color-text-faint)';
        } else if (commentLen < 10) {
          helpText.textContent = `Review must be at least 10 characters (${commentLen}/10).`;
          helpText.style.color = 'var(--color-danger)';
        } else if (commentLen > 1000) {
          helpText.textContent = `Review is too long (${commentLen}/1000 max).`;
          helpText.style.color = 'var(--color-danger)';
        } else {
          helpText.textContent = `✔ ${commentLen} / 1000 characters`;
          helpText.style.color = 'var(--color-primary)';
        }
      }
    }

    if (stars && ratingInput) {
      stars.forEach(star => {
        star.addEventListener('click', () => {
          const val = parseInt(star.dataset.value, 10);
          ratingInput.value = val.toString();
          highlightFormStars(val);
          updateFormValidity();
        });

        star.addEventListener('mouseenter', () => {
          const val = parseInt(star.dataset.value, 10);
          highlightFormStars(val);
        });

        star.addEventListener('mouseleave', () => {
          const currentVal = parseInt(ratingInput.value || '0', 10);
          highlightFormStars(currentVal);
        });
      });
    }

    if (commentInput) {
      commentInput.addEventListener('input', updateFormValidity);
    }
    if (titleInput) {
      titleInput.addEventListener('input', updateFormValidity);
    }

    function highlightFormStars(val) {
      if (!stars) return;
      stars.forEach(star => {
        const starVal = parseInt(star.dataset.value, 10);
        if (starVal <= val) {
          star.classList.remove('far');
          star.classList.add('fas', 'selected');
        } else {
          star.classList.remove('fas', 'selected');
          star.classList.add('far');
        }
      });
    }

    // Initialize stars state if rating value already exists (e.g. edit mode)
    if (ratingInput && parseInt(ratingInput.value, 10) > 0) {
      highlightFormStars(parseInt(ratingInput.value, 10));
      updateFormValidity();
    }

    // ---------------------------------------------------------------------
    // 2. Submit Review Form (Add or Edit)
    // ---------------------------------------------------------------------
    const reviewForm = document.getElementById('productReviewForm');
    reviewForm?.addEventListener('submit', async (e) => {
      e.preventDefault();

      const ratingVal = parseInt(ratingInput?.value || '0', 10);
      const commentVal = commentInput?.value.trim() ?? '';
      const titleVal = titleInput?.value.trim() ?? '';

      if (ratingVal < 1 || ratingVal > 5) {
        window.showToast?.('Please choose a rating score (1-5 stars).', 'error');
        return;
      }

      if (commentVal.length < 10) {
        window.showToast?.('Review comment must be at least 10 characters.', 'error');
        return;
      }

      if (commentVal.length > 1000) {
        window.showToast?.('Review comment cannot exceed 1000 characters.', 'error');
        return;
      }

      const btn = document.getElementById('btnSubmitReview');
      if (!btn || btn.disabled) return;
      const origText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

      // Gather form inputs via FormData
      const formData = new FormData(reviewForm);
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
      formData.append('csrf_token', csrfToken);

      try {
        const res = await fetch('ajax/review.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        });
        
        let json;
        try {
          json = await res.json();
        } catch (parseErr) {
          throw new Error('Invalid server response format.');
        }

        btn.disabled = false;
        btn.innerHTML = origText;

        if (json.success) {
          window.showToast?.(json.message, 'success');
          setTimeout(() => {
            window.location.reload();
          }, 1200);
        } else {
          window.showToast?.(json.message || 'Failed to save review.', 'error');
        }
      } catch (err) {
        btn.disabled = false;
        btn.innerHTML = origText;
        window.showToast?.('Connection error or invalid response. Please try again.', 'error');
      }
    });

    // ---------------------------------------------------------------------
    // 3. Edit Review Handler
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', (e) => {
      const editBtn = e.target.closest('.btn-edit-review');
      if (!editBtn) return;

      const reviewId = editBtn.dataset.reviewId;
      const rating = parseInt(editBtn.dataset.rating || '0', 10);
      const title = editBtn.dataset.title || '';
      const comment = editBtn.dataset.comment || '';

      const formCard = document.getElementById('reviewFormCard');
      const formTitle = document.getElementById('reviewFormTitle');
      const formAction = document.getElementById('reviewFormAction');
      const formReviewIdInput = document.getElementById('reviewFormId');
      const formTitleInput = document.getElementById('reviewTitle');
      const formTextArea = document.getElementById('reviewComment');
      const btnSubmit = document.getElementById('btnSubmitReview');
      const btnCancel = document.getElementById('btnCancelEditReview');

      if (formAction && formReviewIdInput && formTextArea && btnSubmit) {
        // Set fields
        formAction.value = 'edit';
        formReviewIdInput.value = reviewId;
        if (formTitleInput) formTitleInput.value = title;
        formTextArea.value = comment;
        if (ratingInput) ratingInput.value = rating.toString();
        highlightFormStars(rating);
        updateFormValidity();

        // Adjust UI
        if (formTitle) formTitle.textContent = 'Update Your Review';
        btnSubmit.textContent = 'Update Review';
        if (btnCancel) btnCancel.style.display = 'inline-block';
        if (reviewForm) reviewForm.style.display = 'block';

        // Scroll smoothly to form
        formCard?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });

    // Cancel Edit Trigger
    const btnCancelEdit = document.getElementById('btnCancelEditReview');
    btnCancelEdit?.addEventListener('click', () => {
      const formTitle = document.getElementById('reviewFormTitle');
      const formAction = document.getElementById('reviewFormAction');
      const formReviewIdInput = document.getElementById('reviewFormId');
      const formTitleInput = document.getElementById('reviewTitle');
      const formTextArea = document.getElementById('reviewComment');
      const btnSubmit = document.getElementById('btnSubmitReview');

      if (formAction && formReviewIdInput && formTextArea && btnSubmit) {
        formAction.value = 'add';
        formReviewIdInput.value = '';
        if (formTitleInput) formTitleInput.value = '';
        formTextArea.value = '';
        if (ratingInput) ratingInput.value = '0';
        highlightFormStars(0);
        updateFormValidity();

        if (formTitle) formTitle.textContent = 'Write a Customer Review';
        btnSubmit.textContent = 'Submit Review';
        btnCancelEdit.style.display = 'none';
        
        const hasExistingReview = document.querySelector('.verified-purchaser-warning');
        if (hasExistingReview && reviewForm && reviewForm.style.display !== 'none') {
          reviewForm.style.display = 'none';
        }
      }
    });

    // ---------------------------------------------------------------------
    // 4. Delete Review Handler
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const deleteBtn = e.target.closest('.btn-delete-review');
      if (!deleteBtn) return;

      const reviewId = deleteBtn.dataset.reviewId;
      if (!reviewId) return;

      if (confirm('Are you sure you want to delete your review? This action cannot be undone.')) {
        deleteBtn.disabled = true;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('review_id', reviewId);
        formData.append('csrf_token', csrfToken);

        try {
          const res = await fetch('ajax/review.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
          });
          const json = await res.json();
          if (json.success) {
            window.showToast?.(json.message, 'success');
            setTimeout(() => {
              window.location.reload();
            }, 1000);
          } else {
            deleteBtn.disabled = false;
            window.showToast?.(json.message || 'Failed to delete review.', 'error');
          }
        } catch (err) {
          deleteBtn.disabled = false;
          window.showToast?.('Connection error. Please try again.', 'error');
        }
      }
    });

    // ---------------------------------------------------------------------
    // 5. Helpful Vote Handler
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', async (e) => {
      const voteBtn = e.target.closest('.btn-helpful-vote');
      if (!voteBtn || voteBtn.classList.contains('voted') || voteBtn.disabled) return;

      const reviewId = voteBtn.dataset.reviewId;
      if (!reviewId) return;

      voteBtn.classList.add('voted');
      const icon = voteBtn.querySelector('i');
      if (icon) icon.className = 'fas fa-thumbs-up';

      const countEl = voteBtn.querySelector('.helpful-vote-count');
      if (countEl) {
        let count = parseInt(countEl.textContent || '0', 10);
        countEl.textContent = (count + 1).toString();
      }

      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
      const formData = new FormData();
      formData.append('action', 'helpful');
      formData.append('review_id', reviewId);
      formData.append('csrf_token', csrfToken);

      try {
        await fetch('ajax/review.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        });
        window.showToast?.('Thank you for your helpful vote!', 'success');
      } catch (err) {
        // Vote registered locally in UI
      }
    });

    // ---------------------------------------------------------------------
    // 6. Production-Level Review Image Lightbox Modal
    // ---------------------------------------------------------------------
    let lightboxEl = document.getElementById('reviewImageLightbox');
    let currentGallery = [];
    let currentIndex = 0;
    let lastFocusedElement = null;
    let touchStartX = 0;

    function buildLightboxDOM() {
      if (document.getElementById('reviewImageLightbox')) {
        lightboxEl = document.getElementById('reviewImageLightbox');
        return;
      }

      lightboxEl = document.createElement('div');
      lightboxEl.id = 'reviewImageLightbox';
      lightboxEl.className = 'review-image-lightbox';
      lightboxEl.setAttribute('role', 'dialog');
      lightboxEl.setAttribute('aria-modal', 'true');
      lightboxEl.setAttribute('aria-label', 'Customer review image viewer');
      lightboxEl.setAttribute('aria-hidden', 'true');
      lightboxEl.style.display = 'none';

      lightboxEl.innerHTML = `
        <div class="review-lightbox-backdrop" aria-hidden="true"></div>
        <div class="review-lightbox-dialog">
          <button type="button" class="review-lightbox-close" id="reviewLightboxClose" aria-label="Close image lightbox">
            <i class="fas fa-times" aria-hidden="true"></i>
          </button>
          <button type="button" class="review-lightbox-nav prev" id="reviewLightboxPrev" aria-label="Previous image" style="display:none;">
            <i class="fas fa-chevron-left" aria-hidden="true"></i>
          </button>
          <div class="review-lightbox-figure">
            <img src="" alt="Full size customer review photo" class="review-lightbox-img" id="reviewLightboxImg">
            <div class="review-lightbox-counter" id="reviewLightboxCounter" style="display:none;"></div>
          </div>
          <button type="button" class="review-lightbox-nav next" id="reviewLightboxNext" aria-label="Next image" style="display:none;">
            <i class="fas fa-chevron-right" aria-hidden="true"></i>
          </button>
        </div>
      `;

      document.body.appendChild(lightboxEl);

      // Event listeners on lightbox elements
      const closeBtn = lightboxEl.querySelector('#reviewLightboxClose');
      const backdrop = lightboxEl.querySelector('.review-lightbox-backdrop');
      const prevBtn = lightboxEl.querySelector('#reviewLightboxPrev');
      const nextBtn = lightboxEl.querySelector('#reviewLightboxNext');
      const dialog = lightboxEl.querySelector('.review-lightbox-dialog');

      closeBtn?.addEventListener('click', closeReviewLightbox);
      backdrop?.addEventListener('click', closeReviewLightbox);
      prevBtn?.addEventListener('click', (e) => { e.stopPropagation(); prevReviewImage(); });
      nextBtn?.addEventListener('click', (e) => { e.stopPropagation(); nextReviewImage(); });

      // Swipe support for touch devices
      dialog?.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
      }, { passive: true });

      dialog?.addEventListener('touchend', (e) => {
        const touchEndX = e.changedTouches[0].screenX;
        const diff = touchEndX - touchStartX;
        if (diff > 45) {
          prevReviewImage();
        } else if (diff < -45) {
          nextReviewImage();
        }
      }, { passive: true });
    }

    function openReviewLightbox(gallery, startIndex, triggerEl) {
      if (!Array.isArray(gallery) || gallery.length === 0) return;
      buildLightboxDOM();

      currentGallery = gallery;
      currentIndex = Math.max(0, Math.min(startIndex || 0, gallery.length - 1));
      lastFocusedElement = triggerEl || document.activeElement;

      // Lock body scroll safely
      document.body.style.overflow = 'hidden';

      lightboxEl.style.display = 'flex';
      // Trigger reflow for smooth transition
      void lightboxEl.offsetWidth;
      lightboxEl.classList.add('active');
      lightboxEl.setAttribute('aria-hidden', 'false');

      updateLightboxView();

      const closeBtn = lightboxEl.querySelector('#reviewLightboxClose');
      closeBtn?.focus();

      window.addEventListener('keydown', handleLightboxKeydown);
    }

    function updateLightboxView() {
      if (!lightboxEl || currentGallery.length === 0) return;
      const img = lightboxEl.querySelector('#reviewLightboxImg');
      const prevBtn = lightboxEl.querySelector('#reviewLightboxPrev');
      const nextBtn = lightboxEl.querySelector('#reviewLightboxNext');
      const counter = lightboxEl.querySelector('#reviewLightboxCounter');

      if (img) {
        img.src = currentGallery[currentIndex];
      }

      if (currentGallery.length > 1) {
        if (prevBtn) prevBtn.style.display = 'flex';
        if (nextBtn) nextBtn.style.display = 'flex';
        if (counter) {
          counter.style.display = 'block';
          counter.textContent = `${currentIndex + 1} / ${currentGallery.length}`;
        }
      } else {
        if (prevBtn) prevBtn.style.display = 'none';
        if (nextBtn) nextBtn.style.display = 'none';
        if (counter) counter.style.display = 'none';
      }
    }

    function prevReviewImage() {
      if (currentGallery.length <= 1) return;
      currentIndex = (currentIndex - 1 + currentGallery.length) % currentGallery.length;
      updateLightboxView();
    }

    function nextReviewImage() {
      if (currentGallery.length <= 1) return;
      currentIndex = (currentIndex + 1) % currentGallery.length;
      updateLightboxView();
    }

    function closeReviewLightbox() {
      if (!lightboxEl) return;
      lightboxEl.classList.remove('active');
      lightboxEl.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      window.removeEventListener('keydown', handleLightboxKeydown);

      setTimeout(() => {
        if (!lightboxEl.classList.contains('active')) {
          lightboxEl.style.display = 'none';
        }
      }, 200);

      if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
        lastFocusedElement.focus();
      }
    }

    function handleLightboxKeydown(e) {
      if (!lightboxEl || !lightboxEl.classList.contains('active')) return;
      if (e.key === 'Escape') {
        e.preventDefault();
        closeReviewLightbox();
      } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        prevReviewImage();
      } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        nextReviewImage();
      }
    }

    // Event delegation on document.body for any review image thumbnail click
    document.body.addEventListener('click', (e) => {
      const thumbBtn = e.target.closest('.review-image-thumb-btn');
      if (!thumbBtn) return;
      e.preventDefault();

      const grid = thumbBtn.closest('.review-images-grid');
      let gallery = [];

      if (grid && grid.dataset.reviewGallery) {
        try {
          gallery = JSON.parse(grid.dataset.reviewGallery);
        } catch (err) {
          gallery = [];
        }
      }

      if (!gallery || gallery.length === 0) {
        const fullUrl = thumbBtn.dataset.fullUrl || thumbBtn.querySelector('img')?.src;
        if (fullUrl) {
          gallery = [fullUrl];
        }
      }

      const idx = parseInt(thumbBtn.dataset.index || '0', 10);
      openReviewLightbox(gallery, idx, thumbBtn);
    });

    // Expose helpers globally
    window.openReviewLightbox = openReviewLightbox;
    window.closeReviewLightbox = closeReviewLightbox;
  }

  // Safe DOM Load Trigger
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReviews);
  } else {
    initReviews();
  }
})();
