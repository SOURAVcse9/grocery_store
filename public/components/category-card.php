<?php
/**
 * ==========================================================================
 * public/components/category-card.php
 * ==========================================================================
 * Reusable category card component with clean SEO URLs and optimized images.
 * Uses dedicated circular clipping container with perfect 1:1 aspect ratio.
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

$fallbackImageUrl = image_url(null, 'categories');
$imageUrl = !empty($catImage)
    ? CloudinaryService::url($catImage, ['w' => 200, 'h' => 200, 'c' => 'limit', 'f' => 'auto', 'q' => 'auto'], 'categories')
    : $fallbackImageUrl;
$imageSrcset = get_responsive_srcset($catImage, 'categories');
$categoryUrl = category_url($catSlug);
$imageAlt = generate_image_alt($catName, $category['image_alt'] ?? null);
$isFallback = empty($catImage);
?>
<a href="<?= e($categoryUrl) ?>" class="category-card">
    <div class="category-image-wrapper category-image category-card-image-wrapper<?= $isFallback ? ' category-image--fallback' : '' ?>">
        <img class="category-card-image" 
             src="<?= e($imageUrl) ?>" 
             <?php if (!empty($imageSrcset)): ?>srcset="<?= e($imageSrcset) ?>" sizes="(max-width: 576px) 80px, 100px"<?php endif; ?>
             alt="<?= e($imageAlt) ?>" 
             loading="lazy" decoding="async" 
             width="100" height="100"
             onerror="this.onerror=null;this.removeAttribute('srcset');this.src='<?= e($fallbackImageUrl) ?>';">
    </div>
    <div class="category-card-content">
        <h3 class="category-card-title"><?= e($catName) ?></h3>
        <span class="category-card-count"><?= $productCount ?> <?= $productCount === 1 ? (t('item') ?? 'Item') : (t('items') ?? 'Items') ?></span>
    </div>
</a>
