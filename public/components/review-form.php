<?php
/**
 * ==========================================================================
 * public/components/review-form.php
 * ==========================================================================
 * Reusable Review Submission Form.
 * Expects variables:
 *   - $productId (int): ID of the product.
 *   - $hasPurchased (bool): whether the user has purchased the product.
 *   - $existingReview (array|null): user's existing review of this product.
 * ==========================================================================
 */

declare(strict_types=1);

$productId = (int) ($productId ?? 0);
$hasPurchased = (bool) ($hasPurchased ?? false);
$existingReview = $existingReview ?? null;

$isEdit = ($existingReview !== null);
$ratingVal = $existingReview ? (int) $existingReview['rating'] : 0;
$titleVal = $existingReview ? ($existingReview['review_title'] ?? '') : '';
$commentVal = $existingReview ? ($existingReview['review_comment'] ?? '') : '';
$reviewId = $existingReview ? (int) $existingReview['id'] : 0;
?>
<div class="review-form-card" id="reviewFormCard">
    <h3 class="review-form-card-title" id="reviewFormTitle">
        <?= $isEdit ? 'Update Your Review' : 'Write a Customer Review' ?>
    </h3>

    <?php if (!is_logged_in()): ?>
        <p class="review-block-message">
            Please <a href="<?= url_for('login.php') ?>" class="btn btn-primary" style="padding: 6px 16px; border-radius: var(--radius-pill); font-size:12px; font-weight:700; display:inline-block; margin-left:8px; text-decoration:none;">Login to submit your review</a>
        </p>
    <?php elseif (!$hasPurchased): ?>
        <div class="verified-purchaser-warning">
            <i class="fas fa-triangle-exclamation"></i>
            <div>
                <strong>You can review this product after purchasing and receiving it.</strong><br>
                To prevent fake ratings, reviews are restricted to customers who purchased and received this product from our store.
            </div>
        </div>
    <?php else: ?>
        <!-- Active Submission Form (supports binary file uploads) -->
        <form id="productReviewForm" class="auth-form" enctype="multipart/form-data" onsubmit="return false;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="reviewFormAction" value="<?= $isEdit ? 'edit' : 'add' ?>">
            <input type="hidden" name="product_id" value="<?= $productId ?>">
            <input type="hidden" name="review_id" id="reviewFormId" value="<?= $reviewId ?>">

            <!-- Interactive Rating selector -->
            <div class="form-field-group">
                <label style="margin-bottom:4px; font-weight:700;">Your Rating *</label>
                <div class="rating-select-stars" id="formRatingSelectStars">
                    <?php for ($i = 1; $i <= 5; $i++): 
                        $class = $i <= $ratingVal ? 'fas selected' : 'far';
                    ?>
                        <i class="<?= $class ?> fa-star rating-select-star" data-value="<?= $i ?>" title="<?= $i ?> Star<?= $i > 1 ? 's' : '' ?>"></i>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="formRatingInput" value="<?= $ratingVal ?>">
            </div>

            <!-- Review Title -->
            <div class="form-field-group">
                <label for="reviewTitle" style="font-weight:700;">Review Title *</label>
                <input type="text" id="reviewTitle" name="review_title" class="review-form-input" placeholder="Summarize your experience (e.g. Excellent freshness, Highly recommended!)" required value="<?= e($titleVal) ?>" style="width:100%; padding:10px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <!-- Comment box -->
            <div class="form-field-group">
                <label for="reviewComment" style="font-weight:700;">Review Comment *</label>
                <textarea id="reviewComment" name="review" class="review-form-textarea" placeholder="What did you like or dislike about this product? How was the quality?" required minlength="10" maxlength="1000" style="width:100%; min-height:120px; padding:10px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; resize:vertical;"><?= e($commentVal) ?></textarea>
                <span class="field-help-text">Review must be between 10 and 1000 characters.</span>
            </div>

            <!-- Review Image Upload -->
            <div class="form-field-group">
                <label for="reviewImagesInput" style="font-weight:700;">Upload Images (Optional, Max 3 files)</label>
                <input type="file" id="reviewImagesInput" name="review_images[]" accept="image/*" multiple style="font-size:13px; display:block; margin-top:4px;">
                <span class="field-help-text">JPG, JPEG, PNG, or WebP. Maximum size 5MB per file.</span>
            </div>

            <div style="display:flex; gap:var(--space-2); margin-top:var(--space-2);">
                <button type="submit" class="btn btn-primary" id="btnSubmitReview" style="padding:10px 24px; border:none; border-radius:var(--radius-pill); font-weight:700;">
                    <?= $isEdit ? 'Update Review' : 'Submit Review' ?>
                </button>
                
                <button type="button" class="btn btn-secondary" id="btnCancelEditReview" style="padding:10px 24px; border:none; border-radius:var(--radius-pill); font-weight:700; display: <?= $isEdit ? 'block' : 'none' ?>;">
                    Cancel
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>
