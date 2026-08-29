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
  }

  // Safe DOM Load Trigger
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReviews);
  } else {
    initReviews();
  }
})();
