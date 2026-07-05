/**
 * ==========================================================================
 * public/assets/js/reviews.js
 * ==========================================================================
 * Handle client-side product review interactions:
 *   - Clicking star inputs to select rating score
 *   - Edit Review trigger: prefill form inputs and scroll to form container
 *   - Delete Review trigger: call AJAX removal
 *   - Helpful Vote UI toggles (simulated counter)
 * ==========================================================================
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // ---------------------------------------------------------------------
    // 1. Star Rating Input Selector in Form
    // ---------------------------------------------------------------------
    const starContainer = document.getElementById('formRatingSelectStars');
    const stars = starContainer?.querySelectorAll('.rating-select-star');
    const ratingInput = document.getElementById('formRatingInput');

    if (stars && ratingInput) {
      stars.forEach(star => {
        star.addEventListener('click', () => {
          const val = parseInt(star.dataset.value);
          ratingInput.value = val.toString();
          highlightFormStars(val);
        });

        star.addEventListener('mouseenter', () => {
          const val = parseInt(star.dataset.value);
          highlightFormStars(val);
        });

        star.addEventListener('mouseleave', () => {
          const currentVal = parseInt(ratingInput.value || '0');
          highlightFormStars(currentVal);
        });
      });
    }

    function highlightFormStars(val) {
      if (!stars) return;
      stars.forEach(star => {
        const starVal = parseInt(star.dataset.value);
        if (starVal <= val) {
          star.classList.replace('far', 'fas');
          star.classList.add('selected');
        } else {
          star.classList.replace('fas', 'far');
          star.classList.remove('selected');
        }
      });
    }

    // ---------------------------------------------------------------------
    // 2. Submit Review Form (Add or Edit)
    // ---------------------------------------------------------------------
    const reviewForm = document.getElementById('productReviewForm');
    reviewForm?.addEventListener('submit', async (e) => {
      e.preventDefault();

      const ratingVal = parseInt(ratingInput?.value || '0');
      const commentVal = document.getElementById('reviewComment')?.value.trim() ?? '';

      if (ratingVal < 1 || ratingVal > 5) {
        window.showToast?.('Please choose a rating score (1-5 stars).', 'error');
        return;
      }

      if (commentVal.length < 10) {
        window.showToast?.('Review comment must be at least 10 characters.', 'error');
        return;
      }

      const btn = document.getElementById('btnSubmitReview');
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
        const json = await res.json();

        btn.disabled = false;
        btn.innerHTML = origText;

        if (json.success) {
          window.showToast?.(json.message, 'success');
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          window.showToast?.(json.message || 'Failed to save review.', 'error');
        }
      } catch (err) {
        btn.disabled = false;
        btn.innerHTML = origText;
        window.showToast?.('Connection error. Please try again.', 'error');
      }
    });

    // ---------------------------------------------------------------------
    // 3. Edit Review Handler
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', (e) => {
      const editBtn = e.target.closest('.btn-edit-review');
      if (!editBtn) return;

      const reviewId = editBtn.dataset.reviewId;
      const rating = parseInt(editBtn.dataset.rating || '0');
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

      if (formAction && formReviewIdInput && formTextArea && btnSubmit && btnCancel) {
        // Set fields
        formAction.value = 'edit';
        formReviewIdInput.value = reviewId;
        if (formTitleInput) formTitleInput.value = title;
        formTextArea.value = comment;
        if (ratingInput) ratingInput.value = rating.toString();
        highlightFormStars(rating);

        // Adjust UI
        if (formTitle) formTitle.textContent = 'Update Your Review';
        btnSubmit.textContent = 'Update Review';
        btnCancel.style.display = 'block';

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

        if (formTitle) formTitle.textContent = 'Write a Customer Review';
        btnSubmit.textContent = 'Submit Review';
        btnCancelEdit.style.display = 'none';
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

        const json = await window.apiPost('ajax/review.php', {
          action: 'delete',
          review_id: reviewId
        });

        if (json.success) {
          window.showToast?.(json.message, 'success');
          setTimeout(() => {
            window.location.reload();
          }, 1000);
        } else {
          deleteBtn.disabled = false;
          window.showToast?.(json.message || 'Failed to delete review.', 'error');
        }
      }
    });

    // ---------------------------------------------------------------------
    // 5. Helpful Vote Handler (UI Ready / Cookie Saved)
    // ---------------------------------------------------------------------
    document.body.addEventListener('click', (e) => {
      const voteBtn = e.target.closest('.btn-helpful-vote');
      if (!voteBtn || voteBtn.classList.contains('voted')) return;

      const reviewId = voteBtn.dataset.reviewId;
      if (!reviewId) return;

      // Mark voted in UI
      voteBtn.classList.add('voted');
      const icon = voteBtn.querySelector('i');
      if (icon) icon.className = 'fas fa-thumbs-up'; // change outline to solid

      const countEl = voteBtn.querySelector('.helpful-vote-count');
      if (countEl) {
        let count = parseInt(countEl.textContent || '0');
        countEl.textContent = (count + 1).toString();
      }

      window.showToast?.('Thank you for your helpful vote!', 'success');
    });

  });
})();
