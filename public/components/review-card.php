<?php
/**
 * ==========================================================================
 * public/components/review-card.php
 * ==========================================================================
 * Reusable Review Card Component.
 * Expects:
 *   - $review (array): review row details.
 *   - $currentUserId (int|null): currently logged in user id.
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($review) || !is_array($review)) {
    return;
}

$reviewId = (int) $review['id'];
$reviewerId = (int) $review['user_id'];
$rating = (float) $review['rating'];
$title = $review['review_title'] ?? '';
$comment = $review['review_comment'] ?? '';
$date = date('M d, Y', strtotime($review['created_at']));
$reviewerName = $review['full_name'] ?? 'Anonymous';
$avatar = $review['avatar'] ?? null;
$isVerified = (bool) ($review['verified_purchase'] ?? false);
$currentUserId = $currentUserId ?? null;

$isOwnReview = ($currentUserId !== null && $currentUserId === $reviewerId);

// Avatar resolve
$avatarUrl = $avatar ? image_url($avatar, 'users') : asset('images/ui/placeholder.png');
?>
<div class="review-card" data-review-id="<?= $reviewId ?>" id="review-card-<?= $reviewId ?>">
    <!-- Header -->
    <div class="review-card-header">
        <div class="reviewer-avatar">
            <img src="<?= e($avatarUrl) ?>" alt="<?= e($reviewerName) ?>" style="width:40px; height:40px; border-radius:var(--radius-pill); object-fit:cover;">
        </div>
        <div class="reviewer-meta">
            <h4 class="reviewer-name"><?= e($reviewerName) ?></h4>
            <div class="reviewer-sub-row">
                <!-- Stars -->
                <?php include PUBLIC_PATH . '/components/rating-stars.php'; ?>
                <span class="review-date"><?= $date ?></span>
            </div>
        </div>

        <!-- Badges & actions -->
        <div class="review-header-actions">
            <?php if ($isVerified): ?>
                <span class="verified-purchase-badge" title="Verified Purchase: Purchased and delivered"><i class="fas fa-circle-check"></i> Verified Purchase</span>
            <?php endif; ?>
            
            <?php if ($isOwnReview): ?>
                <div class="own-review-actions">
                    <button type="button" class="btn-review-action btn-edit-review" data-review-id="<?= $reviewId ?>" data-rating="<?= $rating ?>" data-title="<?= e($title) ?>" data-comment="<?= e($comment) ?>" title="Edit review"><i class="far fa-edit"></i></button>
                    <button type="button" class="btn-review-action btn-delete-review" data-review-id="<?= $reviewId ?>" title="Delete review"><i class="far fa-trash-can"></i></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Body -->
    <div class="review-card-body" style="margin-top: var(--space-3);">
        <?php if (!empty($title)): ?>
            <h5 class="review-title-heading" style="font-size: var(--fs-sm); font-weight:700; color:var(--color-text); margin-bottom: var(--space-1);"><?= e($title) ?></h5>
        <?php endif; ?>
        
        <?php if (!empty($comment)): ?>
            <p class="review-comment-text" style="font-size: var(--fs-sm); color:var(--color-text-muted); line-height: 1.6; margin-bottom: 0;"><?= nl2br(e($comment)) ?></p>
        <?php endif; ?>

        <!-- Customer Review Images -->
        <?php if (!empty($review['review_images'])): 
            $images = json_decode($review['review_images'], true);
            if (is_array($images) && !empty($images)):
        ?>
            <div class="review-images-grid" style="display:flex; gap:8px; margin-top:8px; margin-bottom:8px; flex-wrap:wrap;">
                <?php foreach ($images as $img): ?>
                    <a href="<?= e(asset($img)) ?>" target="_blank" rel="noopener noreferrer" class="review-image-link" style="display:inline-block; border:1px solid var(--color-border); border-radius:var(--radius-sm); overflow:hidden;">
                        <img src="<?= e(asset($img)) ?>" alt="Customer upload image" style="width:60px; height:60px; object-fit:cover; transition: transform var(--transition-fast);">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; endif; ?>
    </div>

    <!-- Helpful Vote controls -->
    <div class="review-card-footer" style="margin-top: var(--space-3);">
        <span class="helpful-label">Was this review helpful?</span>
        <button type="button" class="btn-helpful-vote" data-review-id="<?= $reviewId ?>" aria-label="Vote Helpful">
            <i class="far fa-thumbs-up"></i> Yes (<span class="helpful-vote-count"><?= (int) ($review['helpful_count'] ?? 0) ?></span>)
        </button>
    </div>
</div>
