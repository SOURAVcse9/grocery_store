<?php
/**
 * ==========================================================================
 * public/components/rating-stars.php
 * ==========================================================================
 * Reusable Rating Stars Renderer.
 * Expects:
 *   - $rating (float): the rating value from 0 to 5.
 * ==========================================================================
 */

declare(strict_types=1);

$val = isset($rating) ? (float) $rating : 0.0;
$fullStars = (int) floor($val);
$halfStar = ($val - $fullStars) >= 0.5 ? 1 : 0;
$emptyStars = 5 - $fullStars - $halfStar;
?>
<div class="rating-stars" title="<?= $val ?> / 5">
    <?php
    for ($i = 0; $i < $fullStars; $i++) {
        echo '<i class="fas fa-star star-filled" style="color:var(--color-warning);"></i>';
    }
    if ($halfStar) {
        echo '<i class="fas fa-star-half-alt star-filled" style="color:var(--color-warning);"></i>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        echo '<i class="far fa-star star-empty" style="color:var(--color-warning);"></i>';
    }
    ?>
</div>
