<?php
/**
 * ==========================================================================
 * public/components/wishlist-card.php
 * ==========================================================================
 * Individual Wishlist Item Card.
 * Expects:
 *   - $product (array): product details.
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($product) || !is_array($product)) {
    return;
}

$productId = (int) $product['id'];
$productName = $product['name'];
$slug = $product['slug'];
$imageUrl = image_url($product['thumbnail'], 'products');
$price = (float) $product['price'];
$discountPrice = $product['discount_price'] !== null ? (float) $product['discount_price'] : null;
$stock = (int) $product['stock'];
$unit = $product['unit'] ?? '1 kg';
$brandName = $product['brand_name'] ?? '';
$categoryName = $product['category_name'] ?? '';

$isOutOfStock = ($stock <= 0);
$stockText = $isOutOfStock ? 'Out of Stock' : 'In Stock';
$stockClass = $isOutOfStock ? 'out-of-stock' : 'in-stock';
?>
<div class="wishlist-item-card" data-product-id="<?= $productId ?>" id="wishlist-item-<?= $productId ?>">
    <div class="wishlist-card-content">
        <!-- Thumbnail -->
        <a href="<?= url_for('product.php?slug=' . e($slug)) ?>" class="wishlist-card-img">
            <img src="<?= e($imageUrl) ?>" alt="<?= e($productName) ?>">
        </a>

        <!-- Metadata -->
        <div class="wishlist-card-details">
            <div class="product-metadata" style="margin-bottom: 2px;">
                <?php if ($categoryName): ?>
                    <span class="product-category-label"><?= e($categoryName) ?></span>
                <?php endif; ?>
                <?php if ($brandName): ?>
                    <span class="product-brand-label"><?= e($brandName) ?></span>
                <?php endif; ?>
            </div>
            
            <h4 class="wishlist-product-title">
                <a href="<?= url_for('product.php?slug=' . e($slug)) ?>"><?= e($productName) ?></a>
            </h4>
            <span class="wishlist-product-unit"><?= e($unit) ?></span>
            <span class="product-stock-status status-<?= $stockClass ?>"><?= $stockText ?></span>
        </div>

        <!-- Price Area -->
        <div class="wishlist-card-price">
            <?php if ($discountPrice !== null): ?>
                <span class="wishlist-price-sale"><?= format_price($discountPrice) ?></span>
                <span class="wishlist-price-original"><?= format_price($price) ?></span>
            <?php else: ?>
                <span class="wishlist-price-main"><?= format_price($price) ?></span>
            <?php endif; ?>
        </div>

        <!-- Action Row -->
        <div class="wishlist-card-actions">
            <?php if ($isOutOfStock): ?>
                <button class="btn btn-secondary btn-wishlist-cart" disabled style="font-size:11px; padding:8px 12px; border:none; border-radius:var(--radius-pill);"><i class="fas fa-ban"></i> Out of Stock</button>
            <?php else: ?>
                <button class="btn btn-primary btn-wishlist-cart" data-product-id="<?= $productId ?>" style="font-size:11px; padding:8px 14px; border:none; border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-cart-shopping"></i> Add to Cart</button>
                <button class="btn btn-secondary btn-wishlist-move" data-product-id="<?= $productId ?>" style="font-size:11px; padding:8px 12px; border:none; border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-truck-ramp-box"></i> Move to Cart</button>
            <?php endif; ?>
            
            <button class="btn-wishlist-remove" data-product-id="<?= $productId ?>" title="Remove from wishlist" aria-label="Remove item"><i class="far fa-trash-can"></i></button>
        </div>
    </div>
</div>
