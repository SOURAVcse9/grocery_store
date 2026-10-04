<?php
/**
 * ==========================================================================
 * public/components/category-card.php
 * ==========================================================================
 * Reusable category card component with clean SEO URLs and optimized images.
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($category) || !is_array($category)) {
    return;
}

$catId = (int) ($category['id'] ?? 0);
$catName = $category['name'] ?? '';
$catSlug = $category['slug'] ?? '';
$catImage = $category['image'] ?? null;
$productCount = (int) ($category['product_count'] ?? 0);

$imageUrl = CloudinaryService::url($catImage, ['w' => 200, 'h' => 200, 'c' => 'fill', 'f' => 'auto', 'q' => 'auto'], 'categories');
$imageSrcset = get_responsive_srcset($catImage, 'categories');
$categoryUrl = category_url($catSlug);
$imageAlt = generate_image_alt($catName, $category['image_alt'] ?? null);
?>
<a href="<?= e($categoryUrl) ?>" class="category-card">
    <div class="category-card-image-wrapper">
        <img class="category-card-image" 
             src="<?= e($imageUrl) ?>" 
             <?php if (!empty($imageSrcset)): ?>srcset="<?= e($imageSrcset) ?>" sizes="(max-width: 576px) 120px, 160px"<?php endif; ?>
             alt="<?= e($imageAlt) ?>" 
             loading="lazy" decoding="async" 
             width="160" height="160"
             style="aspect-ratio: 1 / 1; object-fit: contain;">
    </div>
    <div class="category-card-content">
        <h3 class="category-card-title"><?= e($catName) ?></h3>
        <span class="category-card-count"><?= $productCount ?> <?= $productCount === 1 ? (t('item') ?? 'Item') : (t('items') ?? 'Items') ?></span>
    </div>
</a>
