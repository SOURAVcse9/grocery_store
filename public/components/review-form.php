<?php
/**
 * ==========================================================================
 * public/components/review-form.php
 * ==========================================================================
 * Reusable Review Submission Form.
 * Expects variables:
 *   - $productId (int): ID of the product.
 *   - $isLoggedIn (bool): whether the user is logged in.
 *   - $hasPurchased (bool): whether the user has purchased the product.
 *   - $isDelivered (bool): whether the order status is DELIVERED and payment completed.
 *   - $existingReview (array|null): user's existing review of this product.
 * ==========================================================================
 */

declare(strict_types=1);

$productId = (int) ($productId ?? 0);
$isLoggedIn = (bool) ($isLoggedIn ?? false);
$hasPurchased = (bool) ($hasPurchased ?? false);
$isDelivered = (bool) ($isDelivered ?? false);
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

    <?php if (!$isLoggedIn): ?>
        <!-- CASE 1 -->
        <p class="review-block-message">
            Please <a href="<?= url_for('login.php') ?>" class="btn btn-primary" style="padding: 6px 16px; border-radius: var(--radius-pill); font-size:12px; font-weight:700; display:inline-block; margin-left:8px; text-decoration:none;">Login to submit a review</a>
        </p>

    <?php elseif (!$hasPurchased): ?>
        <!-- CASE 2 -->
        <div class="verified-purchaser-warning" style="background:#fff9db; border:1px solid #ffe066; color:#f08c00; padding:12px; border-radius:var(--radius-sm); display:flex; gap:10px; font-size:var(--fs-sm);">
            <i class="fas fa-triangle-exclamation" style="font-size:18px; margin-top:2px;"></i>
            <div>
                <strong>Only verified buyers can review this product.</strong>
            </div>
        </div>

    <?php elseif (!$isDelivered): ?>
        <!-- CASE 3 -->
        <div class="verified-purchaser-warning" style="background:#e8f4fd; border:1px solid #b3d7f7; color:#1864ab; padding:12px; border-radius:var(--radius-sm); display:flex; gap:10px; font-size:var(--fs-sm);">
            <i class="fas fa-triangle-exclamation" style="font-size:18px; margin-top:2px;"></i>
            <div>
                <strong>You can submit your review after your order has been delivered.</strong>
            </div>
        </div>

    <?php elseif ($isEdit): ?>
        <!-- CASE 5 -->
        <div class="verified-purchaser-warning" style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); display:flex; gap:10px; font-size:var(--fs-sm); margin-bottom: 16px;">
            <i class="fas fa-circle-check" style="font-size:18px; margin-top:2px;"></i>
            <div>
                <strong>✔ You already reviewed this product.</strong>
                <div style="margin-top: 8px; display: flex; gap: 8px;">
                    <button type="button" class="btn btn-secondary btn-edit-review" data-review-id="<?= $reviewId ?>" data-rating="<?= $ratingVal ?>" data-title="<?= e($titleVal) ?>" data-comment="<?= e($commentVal) ?>" style="padding: 4px 12px; font-size: 11px; border-radius: var(--radius-pill); cursor: pointer;"><i class="far fa-edit"></i> Edit Review</button>
                    <button type="button" class="btn btn-secondary btn-delete-review" data-review-id="<?= $reviewId ?>" style="padding: 4px 12px; font-size: 11px; border-radius: var(--radius-pill); cursor: pointer; background: #fff5f5; border-color: #ffe3e3; color: #fa5252;"><i class="far fa-trash-can"></i> Delete Review</button>
                </div>
            </div>
        </div>

        <!-- Hidden edit form container until they click Edit Review -->
        <form id="productReviewForm" class="auth-form" enctype="multipart/form-data" onsubmit="return false;" style="display:none; margin-top: 16px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="reviewFormAction" value="edit">
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
                <input type="text" id="reviewTitle" name="review_title" class="review-form-input" placeholder="Summarize your experience" required value="<?= e($titleVal) ?>" style="width:100%; padding:10px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <!-- Comment box -->
            <div class="form-field-group">
                <label for="reviewComment" style="font-weight:700;">Review Comment *</label>
                <textarea id="reviewComment" name="review" class="review-form-textarea" placeholder="What did you like or dislike?" required minlength="10" maxlength="1000" style="width:100%; min-height:120px; padding:10px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; resize:vertical;"><?= e($commentVal) ?></textarea>
                <span class="field-help-text">Review must be between 10 and 1000 characters.</span>
            </div>

            <!-- Review Image Upload -->
            <div class="form-field-group">
                <label for="reviewImagesInput" style="font-weight:700;">Upload Images (Optional, Max 3 files)</label>
                <input type="file" id="reviewImagesInput" name="review_images[]" accept="image/*" multiple style="font-size:13px; display:block; margin-top:4px;">
            </div>

            <div style="display:flex; gap:var(--space-2); margin-top:var(--space-2);">
                <button type="submit" class="btn btn-primary" id="btnSubmitReview" style="padding:10px 24px; border:none; border-radius:var(--radius-pill); font-weight:700;">
                    Update Review
                </button>
                <button type="button" class="btn btn-secondary" id="btnCancelEditReview" style="padding:10px 24px; border:none; border-radius:var(--radius-pill); font-weight:700;">
                    Cancel
                </button>
            </div>
        </form>

    <?php else: ?>
        <!-- CASE 4 -->
        <form id="productReviewForm" class="auth-form" enctype="multipart/form-data" onsubmit="return false;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="reviewFormAction" value="add">
            <input type="hidden" name="product_id" value="<?= $productId ?>">
            <input type="hidden" name="review_id" id="reviewFormId" value="0">

            <!-- Interactive Rating selector -->
            <div class="form-field-group">
                <label style="margin-bottom:4px; font-weight:700;">Your Rating *</label>
                <div class="rating-select-stars" id="formRatingSelectStars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="far fa-star rating-select-star" data-value="<?= $i ?>" title="<?= $i ?> Star<?= $i > 1 ? 's' : '' ?>"></i>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="formRatingInput" value="0">
            </div>

            <!-- Review Title -->
            <div class="form-field-group">
                <label for="reviewTitle" style="font-weight:700;">Review Title *</label>
                <input type="text" id="reviewTitle" name="review_title" class="review-form-input" placeholder="Summarize your experience" required value="" style="width:100%; padding:10px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
            </div>

            <!-- Comment box -->
            <div class="form-field-group">
                <label for="reviewComment" style="font-weight:700;">Review Comment *</label>
                <textarea id="reviewComment" name="review" class="review-form-textarea" placeholder="What did you like or dislike?" required minlength="10" maxlength="1000" style="width:100%; min-height:120px; padding:10px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; resize:vertical;"></textarea>
                <span class="field-help-text">Review must be between 10 and 1000 characters.</span>
            </div>

            <!-- Review Image Upload -->
            <div class="form-field-group">
                <label for="reviewImagesInput" style="font-weight:700;">Upload Images (Optional, Max 3 files)</label>
                <input type="file" id="reviewImagesInput" name="review_images[]" accept="image/*" multiple style="font-size:13px; display:block; margin-top:4px;">
            </div>

            <div style="display:flex; gap:var(--space-2); margin-top:var(--space-2);">
                <button type="submit" class="btn btn-primary" id="btnSubmitReview" style="padding:10px 24px; border:none; border-radius:var(--radius-pill); font-weight:700;">
                    Submit Review
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>
