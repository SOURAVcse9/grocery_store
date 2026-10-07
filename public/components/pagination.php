<?php
/**
 * ==========================================================================
 * public/components/pagination.php
 * ==========================================================================
 * Reusable pagination template.
 * Expects:
 *   - $currentPage (int): current active page (1-based).
 *   - $totalPages (int): total pages.
 *   - $baseUrl (string): path or filename (e.g. 'products.php').
 *   - $queryParams (array): associative array of current GET parameters.
 * ==========================================================================
 */

declare(strict_types=1);

if (!isset($totalPages) || $totalPages <= 1) {
    return;
}

$currentPage = isset($currentPage) ? (int) $currentPage : 1;
$queryParams = isset($queryParams) ? $queryParams : [];

// Helper function to build page link preserving existing parameters
$buildPageUrl = function(int $page) use ($baseUrl, $queryParams): string {
    $params = $queryParams;
    unset($params['page']);

    $categorySlug = $params['category'] ?? ($_GET['category'] ?? '');
    $brandSlug = $params['brand'] ?? ($_GET['brand'] ?? '');
    
    // Check if other filters are present
    $hasOtherFilters = !empty($params['min_price']) || !empty($params['max_price']) || !empty($params['availability']) || !empty($params['rating']) || !empty($params['discount']) || !empty($params['q']) || (!empty($params['sort']) && $params['sort'] !== 'newest');

    // If clean category pagination with no extra search/filters
    if (!empty($categorySlug) && empty($brandSlug) && !$hasOtherFilters && function_exists('category_url')) {
        return category_url((string)$categorySlug, $page);
    }

    // If clean brand pagination with no extra search/filters
    if (!empty($brandSlug) && empty($categorySlug) && !$hasOtherFilters && function_exists('brand_url')) {
        return brand_url((string)$brandSlug, $page);
    }

    $params['page'] = $page;
    if ($page === 1) {
        unset($params['page']);
    }
    $query = !empty($params) ? ('?' . http_build_query($params)) : '';
    return url_for($baseUrl . $query);
};

$range = 2; // Number of pages to show around current page
?>
<nav class="pagination-nav" aria-label="Page Navigation">
    <ul class="pagination-list">
        <!-- Previous Button -->
        <?php if ($currentPage > 1): ?>
            <li class="pagination-item">
                <a class="pagination-link" href="<?= e($buildPageUrl($currentPage - 1)) ?>" aria-label="Previous Page">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        <?php else: ?>
            <li class="pagination-item disabled">
                <span class="pagination-link" aria-label="Previous Page"><i class="fas fa-chevron-left"></i></span>
            </li>
        <?php endif; ?>

        <!-- Page Numbers -->
        <?php
        $showDots = true;
        for ($i = 1; $i <= $totalPages; $i++):
            // Always show first page, last page, and pages around current page
            if ($i === 1 || $i === $totalPages || ($i >= $currentPage - $range && $i <= $currentPage + $range)):
                $showDots = true;
                $isActive = ($i === $currentPage);
                ?>
                <li class="pagination-item <?= $isActive ? 'active' : '' ?>">
                    <?php if ($isActive): ?>
                        <span class="pagination-link" aria-current="page"><?= $i ?></span>
                    <?php else: ?>
                        <a class="pagination-link" href="<?= e($buildPageUrl($i)) ?>"><?= $i ?></a>
                    <?php endif; ?>
                </li>
                <?php
            else:
                // Show ellipsis if we skipped pages
                if ($showDots):
                    $showDots = false;
                    ?>
                    <li class="pagination-item disabled">
                        <span class="pagination-link pagination-dots">&hellip;</span>
                    </li>
                    <?php
                endif;
            endif;
        endfor;
        ?>

        <!-- Next Button -->
        <?php if ($currentPage < $totalPages): ?>
            <li class="pagination-item">
                <a class="pagination-link" href="<?= e($buildPageUrl($currentPage + 1)) ?>" aria-label="Next Page">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        <?php else: ?>
            <li class="pagination-item disabled">
                <span class="pagination-link" aria-label="Next Page"><i class="fas fa-chevron-right"></i></span>
            </li>
        <?php endif; ?>
    </ul>
</nav>
