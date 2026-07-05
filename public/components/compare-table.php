<?php
/**
 * ==========================================================================
 * public/components/compare-table.php
 * ==========================================================================
 * Side-by-side Product Comparison Table Component.
 * Expects:
 *   - $products (array): list of up to 4 products to compare.
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($products) || !is_array($products)) {
    return;
}

$count = count($products);

if ($count === 0) {
    ?>
    <div class="cart-empty-page" style="box-shadow:none; border:none; background:transparent;">
        <div class="cart-empty-icon" style="color:var(--color-text-faint);"><i class="fas fa-arrows-left-right"></i></div>
        <h2>No Products to Compare</h2>
        <p>Add up to 4 products to your comparison list to compare prices, ratings, and features side-by-side!</p>
        <a href="<?= url_for('products.php') ?>" class="btn btn-primary">Discover Products</a>
    </div>
    <?php
    return;
}
?>

<div class="compare-table-responsive">
    <table class="compare-table">
        <tbody>
            
            <!-- 1. Remove controls & Thumbnails -->
            <tr class="compare-row-image">
                <td class="compare-feature-label">Product Image</td>
                <?php foreach ($products as $p): 
                    $productId = (int) $p['id'];
                    $thumbUrl = image_url($p['thumbnail'], 'products');
                ?>
                    <td class="compare-col-val" data-product-id="<?= $productId ?>">
                        <button type="button" class="btn-compare-remove" data-product-id="<?= $productId ?>" title="Remove product" aria-label="Remove item">
                            <i class="fas fa-circle-xmark"></i> Remove
                        </button>
                        <a href="<?= url_for('product.php?slug=' . e($p['slug'])) ?>" class="compare-product-img-link">
                            <img src="<?= e($thumbUrl) ?>" alt="<?= e($p['name']) ?>">
                        </a>
                    </td>
                <?php endforeach; ?>
            </tr>

            <!-- 2. Product Name -->
            <tr class="compare-row-name">
                <td class="compare-feature-label">Product Name</td>
                <?php foreach ($products as $p): ?>
                    <td class="compare-col-val font-bold">
                        <a href="<?= url_for('product.php?slug=' . e($p['slug'])) ?>" class="compare-product-title-link">
                            <?= e($p['name']) ?>
                        </a>
                    </td>
                <?php endforeach; ?>
            </tr>

            <!-- 3. Price Block -->
            <tr class="compare-row-price">
                <td class="compare-feature-label">Price</td>
                <?php foreach ($products as $p): 
                    $price = (float) $p['price'];
                    $discountPrice = $p['discount_price'] !== null ? (float) $p['discount_price'] : null;
                ?>
                    <td class="compare-col-val">
                        <?php if ($discountPrice !== null): ?>
                            <span class="compare-price-sale"><?= format_price($discountPrice) ?></span>
                            <span class="compare-price-original"><?= format_price($price) ?></span>
                        <?php else: ?>
                            <span class="compare-price-main"><?= format_price($price) ?></span>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>

            <!-- 4. Brand Name -->
            <tr class="compare-row-brand">
                <td class="compare-feature-label">Brand</td>
                <?php foreach ($products as $p): ?>
                    <td class="compare-col-val text-faint font-bold">
                        <?= e($p['brand_name'] ?? 'Other') ?>
                    </td>
                <?php endforeach; ?>
            </tr>

            <!-- 5. Category Name -->
            <tr class="compare-row-category">
                <td class="compare-feature-label">Category</td>
                <?php foreach ($products as $p): ?>
                    <td class="compare-col-val text-muted">
                        <?= e($p['category_name'] ?? 'Uncategorized') ?>
                    </td>
                <?php endforeach; ?>
            </tr>

            <!-- 6. Rating & Reviews count -->
            <tr class="compare-row-rating">
                <td class="compare-feature-label">Rating</td>
                <?php foreach ($products as $p): 
                    $rating = (float) $p['avg_rating'];
                    $reviewCount = (int) $p['review_count'];
                ?>
                    <td class="compare-col-val">
                        <div style="display:flex; flex-direction:column; align-items:center; gap:2px;">
                            <?php include PUBLIC_PATH . '/components/rating-stars.php'; ?>
                            <span style="font-size:10px; color:var(--color-text-faint); font-weight:700;">(<?= $reviewCount ?> reviews)</span>
                        </div>
                    </td>
                <?php endforeach; ?>
            </tr>

            <!-- 7. Stock Status -->
            <tr class="compare-row-stock">
                <td class="compare-feature-label">Availability</td>
                <?php foreach ($products as $p): 
                    $stock = (int) $p['stock'];
                    $isOutOfStock = ($stock <= 0);
                ?>
                    <td class="compare-col-val">
                        <span class="product-stock-status status-<?= $isOutOfStock ? 'out-of-stock' : 'in-stock' ?>">
                            <?= $isOutOfStock ? 'Out of Stock' : 'In Stock (' . $stock . ')' ?>
                        </span>
                    </td>
                <?php endforeach; ?>
            </tr>

            <!-- 8. Weight / Unit -->
            <tr class="compare-row-unit">
                <td class="compare-feature-label">Unit Size</td>
                <?php foreach ($products as $p): ?>
                    <td class="compare-col-val text-muted">
                        <?= e($p['unit'] ?? '1 kg') ?>
                    </td>
                <?php endforeach; ?>
            </tr>

            <!-- 9. SKU Code -->
            <tr class="compare-row-sku">
                <td class="compare-feature-label">Product SKU</td>
                <?php foreach ($products as $p): ?>
                    <td class="compare-col-val font-mono" style="font-size:10px;">
                        <?= e($p['sku']) ?>
                    </td>
                <?php endforeach; ?>
            </tr>

            <!-- 10. Description Summary -->
            <tr class="compare-row-desc">
                <td class="compare-feature-label">Description</td>
                <?php foreach ($products as $p): ?>
                    <td class="compare-col-val text-muted text-left" style="font-size:11px; line-height:1.4; vertical-align:top; max-width:240px; min-width:160px;">
                        <?= e(mb_strimwidth($p['description'] ?? 'No description registered.', 0, 120, '...')) ?>
                    </td>
                <?php endforeach; ?>
            </tr>

            <!-- 11. Add to Cart CTAs -->
            <tr class="compare-row-cart">
                <td class="compare-feature-label">Cart Action</td>
                <?php foreach ($products as $p): 
                    $productId = (int) $p['id'];
                    $isOutOfStock = ((int)$p['stock'] <= 0);
                ?>
                    <td class="compare-col-val">
                        <?php if ($isOutOfStock): ?>
                            <button class="btn btn-secondary btn-compare-cart" disabled style="font-size:11px; border:none; padding:8px 12px; border-radius:var(--radius-pill);"><i class="fas fa-ban"></i> Out of Stock</button>
                        <?php else: ?>
                            <button class="btn btn-primary btn-compare-cart" data-product-id="<?= $productId ?>" style="font-size:11px; border:none; padding:8px 14px; border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-cart-shopping"></i> Add to Cart</button>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>

        </tbody>
    </table>
</div>
