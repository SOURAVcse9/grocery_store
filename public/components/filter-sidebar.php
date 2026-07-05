<?php
/**
 * ==========================================================================
 * public/components/filter-sidebar.php
 * ==========================================================================
 * Reusable filter sidebar panel component.
 * Expects variables:
 *   - $categoriesList (array)
 *   - $brandsList (array)
 *   - $categorySlug (string)
 *   - $brandSlug (string)
 *   - $minPrice (numeric)
 *   - $maxPrice (numeric)
 *   - $inStock (bool)
 *   - $discounted (bool)
 *   - $minRating (int)
 * ==========================================================================
 */

declare(strict_types=1);

$categorySlug = $categorySlug ?? '';
$brandSlug = $brandSlug ?? '';
$minPrice = $minPrice ?? '';
$maxPrice = $maxPrice ?? '';
$inStock = $inStock ?? false;
$discounted = $discounted ?? false;
$minRating = $minRating ?? 0;
?>
<aside class="filter-sidebar" id="filterSidebar">
    <div class="filter-sidebar-header">
        <h3>Filters</h3>
        <button type="button" class="btn-clear-filters" id="btnClearFilters">Clear All</button>
    </div>

    <form id="filterForm" onsubmit="return false;">
        <!-- Category Filter -->
        <?php if (!empty($categoriesList)): ?>
            <div class="filter-block">
                <div class="filter-title">Categories</div>
                <div class="filter-list">
                    <?php foreach ($categoriesList as $cat): ?>
                        <label class="filter-option">
                            <input type="radio" name="category" value="<?= e($cat['slug']) ?>" <?= $categorySlug === $cat['slug'] ? 'checked' : '' ?>>
                            <span><?= e($cat['name']) ?></span>
                            <span class="filter-count">(<?= $cat['p_count'] ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Brand Filter -->
        <?php if (!empty($brandsList)): ?>
            <div class="filter-block">
                <div class="filter-title">Brands</div>
                <div class="filter-list">
                    <?php foreach ($brandsList as $b): ?>
                        <label class="filter-option">
                            <input type="radio" name="brand" value="<?= e($b['slug']) ?>" <?= $brandSlug === $b['slug'] ? 'checked' : '' ?>>
                            <span><?= e($b['name']) ?></span>
                            <span class="filter-count">(<?= $b['p_count'] ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Price Range Filter -->
        <div class="filter-block">
            <div class="filter-title">Price Range</div>
            <div class="price-inputs">
                <input type="number" name="min_price" placeholder="Min" min="0" value="<?= e($minPrice) ?>" aria-label="Minimum price">
                <span class="price-divider">&mdash;</span>
                <input type="number" name="max_price" placeholder="Max" min="0" value="<?= e($maxPrice) ?>" aria-label="Maximum price">
            </div>
        </div>

        <!-- Availability Filter -->
        <div class="filter-block">
            <div class="filter-title">Availability</div>
            <label class="filter-option">
                <input type="checkbox" name="availability" value="in_stock" <?= $inStock ? 'checked' : '' ?>>
                <span>In Stock Only</span>
            </label>
        </div>

        <!-- Discount Filter -->
        <div class="filter-block">
            <div class="filter-title">Offers</div>
            <label class="filter-option">
                <input type="checkbox" name="discount" value="on_sale" <?= $discounted ? 'checked' : '' ?>>
                <span>On Discount / Sale</span>
            </label>
        </div>

        <!-- Rating Filter -->
        <div class="filter-block">
            <div class="filter-title">Customer Rating</div>
            <div class="filter-list">
                <?php for ($r = 4; $r >= 2; $r--): ?>
                    <label class="filter-option">
                        <input type="radio" name="rating" value="<?= $r ?>" <?= $minRating === $r ? 'checked' : '' ?>>
                        <span class="rating-filter-row">
                            <span class="rating-stars">
                                <?php
                                for ($s = 1; $s <= 5; $s++) {
                                    if ($s <= $r) {
                                        echo '<i class="fas fa-star star-filled" style="color:var(--color-warning);"></i>';
                                    } else {
                                        echo '<i class="far fa-star star-empty" style="color:var(--color-warning);"></i>';
                                    }
                                }
                                ?>
                            </span>
                            <span>&amp; Up</span>
                        </span>
                    </label>
                <?php endfor; ?>
            </div>
        </div>
    </form>
</aside>
