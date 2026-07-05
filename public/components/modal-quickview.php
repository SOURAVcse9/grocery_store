<?php
/**
 * ==========================================================================
 * public/components/modal-quickview.php
 * ==========================================================================
 * Quick View Modal interior content template.
 * Expects:
 *   - $product (array): detailed product data row.
 *   - $gallery (array): list of image URLs.
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($product) || !is_array($product)) {
    return;
}

$productId = (int) $product['id'];
$productName = $product['name'] ?? '';
$productSlug = $product['slug'] ?? '';
$description = $product['description'] ?? '';
$shortDescription = $product['short_description'] ?? '';
$sku = $product['sku'] ?? '';
$barcode = $product['barcode'] ?? '';
$price = (float) ($product['price'] ?? 0);
$discountPrice = isset($product['discount_price']) && $product['discount_price'] !== null ? (float) $product['discount_price'] : null;
$unit = $product['unit'] ?? 'pcs';
$stock = (int) ($product['stock'] ?? 0);
$brandName = $product['brand_name'] ?? null;
$categoryName = $product['category_name'] ?? '';

$avgRating = (float) ($product['avg_rating'] ?? 0.0);
$reviewCount = (int) ($product['review_count'] ?? 0);

$discountPercent = 0;
if ($discountPrice !== null && $price > 0 && $discountPrice < $price) {
    $discountPercent = (int) round((($price - $discountPrice) / $price) * 100);
}

$inStock = $stock > 0;
$stockClass = $inStock ? 'status-in-stock' : 'status-out-of-stock';
$stockText = $inStock ? 'In Stock' : 'Out of Stock';
?>
<div class="quickview-modal-content">
    <!-- Left: Gallery -->
    <div class="quickview-gallery">
        <div class="quickview-main-image-wrapper">
            <?php 
            $mainImg = !empty($gallery) ? $gallery[0] : null;
            $mainImgUrl = image_url($mainImg, 'products');
            ?>
            <img id="qvMainImage" src="<?= e($mainImgUrl) ?>" alt="<?= e($productName) ?>">
        </div>
        
        <?php if (!empty($gallery) && count($gallery) > 1): ?>
            <div class="quickview-thumbnails">
                <?php foreach ($gallery as $index => $img): 
                    $thumbUrl = image_url($img, 'products');
                ?>
                    <button type="button" class="qv-thumb-btn <?= $index === 0 ? 'active' : '' ?>" data-large-url="<?= e($thumbUrl) ?>" aria-label="View gallery image <?= $index + 1 ?>">
                        <img src="<?= e($thumbUrl) ?>" alt="Thumb <?= $index + 1 ?>">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Product Info -->
    <div class="quickview-info">
        <div class="qv-header">
            <?php if ($brandName): ?>
                <span class="qv-brand"><?= e($brandName) ?></span>
            <?php endif; ?>
            <h2 class="qv-title"><?= e($productName) ?></h2>
            <div class="qv-meta-row">
                <span class="qv-category">Category: <strong><?= e($categoryName) ?></strong></span>
                <span class="qv-stock-badge <?= $stockClass ?>"><i class="fas <?= $inStock ? 'fa-check-circle' : 'fa-times-circle' ?>"></i> <?= $stockText ?> (<?= $stock ?> <?= $unit ?> available)</span>
            </div>
        </div>

        <!-- Rating -->
        <div class="qv-rating">
            <div class="rating-stars" title="<?= $avgRating ?> / 5">
                <?php
                $fullStars = (int) floor($avgRating);
                $halfStar = ($avgRating - $fullStars) >= 0.5 ? 1 : 0;
                $emptyStars = 5 - $fullStars - $halfStar;

                for ($i = 0; $i < $fullStars; $i++) {
                    echo '<i class="fas fa-star star-filled"></i>';
                }
                if ($halfStar) {
                    echo '<i class="fas fa-star-half-alt star-filled"></i>';
                }
                for ($i = 0; $i < $emptyStars; $i++) {
                    echo '<i class="far fa-star star-empty"></i>';
                }
                ?>
            </div>
            <span class="review-count"><?= $avgRating > 0 ? number_format($avgRating, 1) : 'No' ?> rating (<?= $reviewCount ?> reviews)</span>
        </div>

        <!-- Price Area -->
        <div class="qv-price-row">
            <div class="qv-price-wrapper">
                <?php if ($discountPrice !== null): ?>
                    <span class="qv-price-sale"><?= format_price($discountPrice) ?></span>
                    <span class="qv-price-original"><?= format_price($price) ?></span>
                    <span class="qv-discount-badge">-<?= $discountPercent ?>% OFF</span>
                <?php else: ?>
                    <span class="qv-price-regular"><?= format_price($price) ?></span>
                <?php endif; ?>
            </div>
            <span class="qv-unit">Price per <?= e($unit) ?></span>
        </div>

        <!-- Description -->
        <div class="qv-description">
            <p><?= e(!empty($shortDescription) ? $shortDescription : truncate($description, 180)) ?></p>
        </div>

        <!-- Checkout / Buy actions -->
        <?php if ($inStock): ?>
            <div class="qv-actions-form">
                <div class="qv-quantity-selector">
                    <button type="button" class="qv-qty-btn qv-qty-minus" aria-label="Decrease Quantity"><i class="fas fa-minus"></i></button>
                    <input type="number" id="qvQtyInput" class="qv-qty-input" value="1" min="1" max="<?= $stock ?>" readonly>
                    <button type="button" class="qv-qty-btn qv-qty-plus" aria-label="Increase Quantity"><i class="fas fa-plus"></i></button>
                </div>

                <div class="qv-buy-buttons">
                    <button type="button" class="btn btn-add-cart btn-primary qv-btn-add" data-product-id="<?= $productId ?>">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                    <button type="button" class="btn btn-buy-now btn-accent qv-btn-buy" data-product-id="<?= $productId ?>">
                        Buy Now
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="qv-actions-form">
                <button type="button" class="btn btn-out-stock" style="width: 100%;" disabled>
                    <i class="fas fa-ban"></i> Out of Stock
                </button>
            </div>
        <?php endif; ?>

        <!-- Utility Row -->
        <div class="qv-utilities">
            <button type="button" class="util-btn btn-wishlist" data-product-id="<?= $productId ?>">
                <i class="far fa-heart"></i> Add to Wishlist
            </button>
            <button type="button" class="util-btn btn-compare" data-product-id="<?= $productId ?>">
                <i class="fas fa-code-compare"></i> Compare Product
            </button>
        </div>

        <!-- Specs footer -->
        <div class="qv-specs">
            <div>SKU: <span><?= e($sku) ?></span></div>
            <?php if ($barcode): ?>
                <div>Barcode: <span><?= e($barcode) ?></span></div>
            <?php endif; ?>
        </div>
    </div>
</div>
