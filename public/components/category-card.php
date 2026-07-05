<?php
/**
 * ==========================================================================
 * public/components/category-card.php
 * ==========================================================================
 * Reusable category card component.
 * Expects:
 *   - $category (array): category data row from the database.
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($category) || !is_array($category)) {
    return;
}

$catId = (int) $category['id'];
$catName = $category['name'] ?? '';
$catSlug = $category['slug'] ?? '';
$catImage = $category['image'] ?? null;
$productCount = (int) ($category['product_count'] ?? 0);

$imageUrl = image_url($catImage, 'categories');
$categoryUrl = url_for('products.php?category=' . e($catSlug));
?>
<a href="<?= $categoryUrl ?>" class="category-card">
    <div class="category-card-image-wrapper">
        <img class="category-card-image lazy" src="<?= asset('images/ui/placeholder.png') ?>" data-src="<?= e($imageUrl) ?>" alt="<?= e($catName) ?>" loading="lazy">
    </div>
    <div class="category-card-content">
        <h3 class="category-card-title"><?= e($catName) ?></h3>
        <span class="category-card-count"><?= $productCount ?> <?= $productCount === 1 ? (t('item') ?? 'Item') : (t('items') ?? 'Items') ?></span>
    </div>
</a>
