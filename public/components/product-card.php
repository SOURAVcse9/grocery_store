<?php
/**
 * ==========================================================================
 * public/components/product-card.php
 * ==========================================================================
 * Reusable product card component.
 * Expects:
 *   - $product (array): product data row from the database.
 * ==========================================================================
 */

declare(strict_types=1);

// Helper check
if (!isset($product) || !is_array($product)) {
    return;
}

$productId = (int) ($product['id'] ?? $product['product_id']);
$productName = $product['name'] ?? '';
$productSlug = $product['slug'] ?? '';
$price = (float) ($product['price'] ?? 0);
$discountPrice = isset($product['discount_price']) && $product['discount_price'] !== null ? (float) $product['discount_price'] : null;
$unit = $product['unit'] ?? 'pcs';
$stock = (int) ($product['stock'] ?? 0);
$thumbnail = $product['thumbnail'] ?? null;
$isFeatured = (bool) ($product['is_featured'] ?? false);

// Rating & reviews
$avgRating = (float) ($product['avg_rating'] ?? 0.0);
$reviewCount = (int) ($product['review_count'] ?? 0);

// Brand & Category
$brandName = $product['brand_name'] ?? null;
$categoryName = $product['category_name'] ?? null;

// Calculate discount percentage
$discountPercent = 0;
if ($discountPrice !== null && $price > 0 && $discountPrice < $price) {
    $discountPercent = (int) round((($price - $discountPrice) / $price) * 100);
}

// In stock status
$inStock = $stock > 0;
$stockClass = $inStock ? 'in-stock' : 'out-of-stock';
$stockText = $inStock ? (t('in_stock') ?? 'In Stock') : (t('out_of_stock') ?? 'Out of Stock');

// Thumbnail image url
$imageUrl = image_url($thumbnail, 'products');
$productUrl = url_for('product.php?slug=' . e($productSlug));
?>
<div class="product-card" data-id="<?= $productId ?>" data-name="<?= e($productName) ?>">
    <!-- Badges Container -->
    <div class="product-badges">
        <?php if ($discountPercent > 0): ?>
            <span class="badge badge-discount">-<?= $discountPercent ?>%</span>
        <?php endif; ?>
        <?php if ($isFeatured): ?>
            <span class="badge badge-featured"><?= t('featured') ?? 'Featured' ?></span>
        <?php endif; ?>
    </div>

    <!-- Quick action buttons overlay -->
    <div class="product-actions-overlay">
        <button type="button" class="action-btn btn-wishlist" data-product-id="<?= $productId ?>" title="<?= e(t('add_to_wishlist') ?? 'Add to Wishlist') ?>" aria-label="Add to Wishlist">
            <i class="far fa-heart"></i>
        </button>
        <button type="button" class="action-btn btn-compare" data-product-id="<?= $productId ?>" title="<?= e(t('add_to_compare') ?? 'Add to Compare') ?>" aria-label="Add to Compare">
            <i class="fas fa-code-compare"></i>
        </button>
        <button type="button" class="action-btn btn-quickview" data-product-id="<?= $productId ?>" title="<?= e(t('quick_view') ?? 'Quick View') ?>" aria-label="Quick View">
            <i class="far fa-eye"></i>
        </button>
    </div>

    <!-- Image Area -->
    <a href="<?= $productUrl ?>" class="product-image-wrapper">
        <img class="product-image lazy" src="<?= asset('images/ui/placeholder.png') ?>" data-src="<?= e($imageUrl) ?>" alt="<?= e($productName) ?>" loading="lazy">
    </a>

    <!-- Content Area -->
    <div class="product-content">
        <div class="product-metadata">
            <?php if ($categoryName): ?>
                <span class="product-category-label"><?= e($categoryName) ?></span>
            <?php endif; ?>
            <?php if ($brandName): ?>
                <span class="product-brand-label"><?= e($brandName) ?></span>
            <?php endif; ?>
        </div>

        <span class="product-stock-status status-<?= $stockClass ?>"><?= e($stockText) ?></span>
        
        <h3 class="product-title">
            <a href="<?= $productUrl ?>"><?= e($productName) ?></a>
        </h3>

        <!-- Rating -->
        <div class="product-rating">
            <?php
            $rating = $avgRating;
            include PUBLIC_PATH . '/components/rating-stars.php';
            ?>
            <span class="review-count">(<?= $reviewCount ?>)</span>
        </div>

        <span class="product-unit"><?= e($unit) ?></span>

        <!-- Price Area -->
        <div class="product-price-wrapper">
            <?php if ($discountPrice !== null): ?>
                <span class="product-price price-sale"><?= format_price($discountPrice) ?></span>
                <span class="product-price price-original"><?= format_price($price) ?></span>
            <?php else: ?>
                <span class="product-price"><?= format_price($price) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Card Footer / Buy Buttons -->
    <div class="product-card-footer">
        <?php if ($inStock): ?>
            <button type="button" class="btn btn-add-cart btn-primary" data-product-id="<?= $productId ?>">
                <i class="fas fa-cart-plus"></i> <?= t('add_to_cart') ?? 'Add to Cart' ?>
            </button>
            <button type="button" class="btn btn-buy-now btn-accent" data-product-id="<?= $productId ?>">
                <?= t('buy_now') ?? 'Buy Now' ?>
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-out-stock" disabled>
                <i class="fas fa-ban"></i> <?= t('out_of_stock') ?? 'Out of Stock' ?>
            </button>
        <?php endif; ?>
    </div>
</div>
