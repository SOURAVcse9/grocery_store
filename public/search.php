<?php
/**
 * ==========================================================================
 * public/search.php — Advanced Search Landing Page
 * ==========================================================================
 * Displays query results, sidebar filters, sorting options, and displays
 * recent/popular search pills on empty states.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$searchQuery = trim(input('q', '', 'get'));
$categorySlug = input('category', '', 'get');
$brandSlug = input('brand', '', 'get');
$minPrice = input('min_price', '', 'get');
$maxPrice = input('max_price', '', 'get');
$inStock = input('availability', '', 'get') === 'in_stock';
$minRating = (int) input('rating', '0', 'get');
$discounted = input('discount', '', 'get') === 'on_sale';
$sort = input('sort', 'newest', 'get');
$page = (int) input('page', '1', 'get');

if ($page < 1) {
    $page = 1;
}

$limit = 9;
$offset = ($page - 1) * $limit;

$totalProducts = 0;
$products = [];
$categoriesList = [];
$brandsList = [];

try {
    $pdo = db();

    // 1. Fetch checklists for Filter Sidebar
    $categoriesList = $pdo->query('
        SELECT c.id, c.name, c.slug, 
               (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS p_count
        FROM categories c 
        WHERE c.is_active = 1 AND c.parent_id IS NULL
        ORDER BY c.name ASC
    ')->fetchAll();

    $brandsList = $pdo->query('
        SELECT b.id, b.name, b.slug,
               (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.is_active = 1) AS p_count
        FROM brands b
        ORDER BY b.name ASC
    ')->fetchAll();

    // 2. Perform Search Queries if query is provided
    if (mb_strlen($searchQuery) >= 2) {
        
        $where = ['p.is_active = 1'];
        $queryParams = [];

        // Match terms
        $where[] = '(p.name LIKE :search_term1 OR p.sku LIKE :search_term2 OR b.name LIKE :search_term3 OR c.name LIKE :search_term4)';
        $term = '%' . $searchQuery . '%';
        $queryParams['search_term1'] = $term;
        $queryParams['search_term2'] = $term;
        $queryParams['search_term3'] = $term;
        $queryParams['search_term4'] = $term;

        // Apply dynamic sidebar filters
        if (!empty($categorySlug)) {
            $where[] = 'c.slug = :category_slug';
            $queryParams['category_slug'] = $categorySlug;
        }

        if (!empty($brandSlug)) {
            $where[] = 'b.slug = :brand_slug';
            $queryParams['brand_slug'] = $brandSlug;
        }

        if (is_numeric($minPrice)) {
            $where[] = 'COALESCE(p.discount_price, p.price) >= :min_price';
            $queryParams['min_price'] = (float) $minPrice;
        }
        if (is_numeric($maxPrice)) {
            $where[] = 'COALESCE(p.discount_price, p.price) <= :max_price';
            $queryParams['max_price'] = (float) $maxPrice;
        }

        if ($inStock) {
            $where[] = 'p.stock > 0';
        }

        if ($discounted) {
            $where[] = 'p.discount_price IS NOT NULL AND p.discount_price < p.price';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Count totals
        if ($minRating > 0) {
            $countSql = "
                SELECT COUNT(*) FROM (
                    SELECT p.id 
                    FROM products p
                    LEFT JOIN categories c ON c.id = p.category_id
                    LEFT JOIN brands b ON b.id = p.brand_id
                    LEFT JOIN product_reviews pr ON pr.product_id = p.id
                    {$whereClause}
                    GROUP BY p.id
                    HAVING COALESCE(AVG(pr.rating), 0) >= :min_rating
                ) as t
            ";
            $queryParams['min_rating'] = $minRating;
        } else {
            $countSql = "
                SELECT COUNT(DISTINCT p.id) 
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                {$whereClause}
            ";
        }

        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($queryParams);
        $totalProducts = (int) $countStmt->fetchColumn();

        // Sort clauses
        $orderBy = 'p.created_at DESC, p.id DESC';
        if ($sort === 'price_asc') {
            $orderBy = 'COALESCE(p.discount_price, p.price) ASC, p.id DESC';
        } elseif ($sort === 'price_desc') {
            $orderBy = 'COALESCE(p.discount_price, p.price) DESC, p.id DESC';
        } elseif ($sort === 'popular') {
            $orderBy = 'review_count DESC, p.id DESC';
        } elseif ($sort === 'best_seller') {
            $orderBy = 'sales_count DESC, p.id DESC';
        } elseif ($sort === 'rating') {
            $orderBy = 'avg_rating DESC, review_count DESC, p.id DESC';
        }

        // Fetch matching products
        $productsSql = "
            SELECT p.*,
                   c.name AS category_name,
                   c.slug AS category_slug,
                   b.name AS brand_name,
                   COALESCE(AVG(pr.rating), 0) AS avg_rating,
                   COUNT(pr.id) AS review_count,
                   (SELECT COUNT(*) FROM order_items WHERE product_id = p.id) AS sales_count
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN brands b ON b.id = p.brand_id
            LEFT JOIN product_reviews pr ON pr.product_id = p.id
            {$whereClause}
            GROUP BY p.id
        ";

        if ($minRating > 0) {
            $productsSql .= " HAVING avg_rating >= :min_rating";
        }

        $productsSql .= " ORDER BY {$orderBy} LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($productsSql);

        foreach ($queryParams as $key => $val) {
            if (is_int($val)) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $val);
            }
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $products = $stmt->fetchAll();

        // Save to Recent Searches list in session
        if (empty($_SESSION['recent_searches'])) {
            $_SESSION['recent_searches'] = [];
        }
        $_SESSION['recent_searches'] = array_diff($_SESSION['recent_searches'], [$searchQuery]);
        array_unshift($_SESSION['recent_searches'], $searchQuery);
        $_SESSION['recent_searches'] = array_slice($_SESSION['recent_searches'], 0, 5);
    }

} catch (PDOException $e) {
    error_log('[search.php] Error: ' . $e->getMessage());
    if (APP_DEBUG) {
        die('Search database error: ' . htmlspecialchars($e->getMessage()));
    }
}

// Layout metadata
$pageTitle = 'Search Results for "' . e($searchQuery) . '" — ' . site_name();
$pageDescription = 'Search results matching your keyword in our grocery e-commerce catalog.';

$extraStylesheets = ['css/home.css', 'css/products.css', 'css/search.css'];
$extraScripts = [
    'js/quickview.js',
    'js/search.js',
    'js/cart.js',
    'js/wishlist.js',
    'js/compare.js'
];

require_once __DIR__ . '/header.php';

// Prepare Breadcrumbs trail
$breadcrumbs = [
    ['title' => 'Search Results: "' . $searchQuery . '"']
];

$popularSearches = ['Apples', 'Organic Milk', 'Bananas', 'Onions', 'Ghee', 'Rice', 'Lentils'];
?>

<!-- Breadcrumbs -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<div class="container" id="catalogWrapper">
    
    <!-- Header pills: Recent & Popular Searches -->
    <div class="search-pills-box">
        <!-- Recent searches -->
        <?php if (!empty($_SESSION['recent_searches'])): ?>
            <div style="margin-bottom: var(--space-3);">
                <span class="search-pills-title"><i class="fas fa-history"></i> Recent Searches</span>
                <div class="search-pills-list">
                    <?php foreach ($_SESSION['recent_searches'] as $rs): ?>
                        <a href="<?= url_for('search.php?q=' . urlencode($rs)) ?>" class="search-pill-tag">
                            <i class="fas fa-magnifying-glass"></i> <?= e($rs) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Popular searches -->
        <div>
            <span class="search-pills-title"><i class="fas fa-fire-flame-curved"></i> Popular Searches</span>
            <div class="search-pills-list">
                <?php foreach ($popularSearches as $ps): ?>
                    <a href="<?= url_for('search.php?q=' . urlencode($ps)) ?>" class="search-pill-tag">
                        <i class="fas fa-magnifying-glass"></i> <?= e($ps) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Core Layout -->
    <div class="catalog-layout">
        
        <!-- Left: Filters Sidebar Panel -->
        <?php include PUBLIC_PATH . '/components/filter-sidebar.php'; ?>

        <!-- Right: Search Content Area -->
        <main class="catalog-main-content">
            
            <!-- Toolbar -->
            <div class="catalog-toolbar">
                <div class="toolbar-left">
                    <button type="button" class="btn-mobile-filter" id="btnMobileFilter">
                        <i class="fas fa-filter"></i> Filters
                    </button>
                    
                    <span class="product-count-label" id="productCountLabel">
                        <?php
                        $start = $totalProducts === 0 ? 0 : $offset + 1;
                        $end = min($offset + $limit, $totalProducts);
                        echo "Showing {$start}-{$end} of {$totalProducts} results";
                        ?>
                    </span>

                    <!-- Grid / List View Toggles -->
                    <div class="view-mode-toggles">
                        <button type="button" class="view-btn" id="btnGridView" title="Grid View" aria-label="Grid View">
                            <i class="fas fa-th-large"></i>
                        </button>
                        <button type="button" class="view-btn" id="btnListView" title="List View" aria-label="List View">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>

                <div class="toolbar-right">
                    <label for="sortSelect" class="sort-label">Sort By:</label>
                    <select id="sortSelect" class="sort-select">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest Arrivals</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Popularity</option>
                        <option value="best_seller" <?= $sort === 'best_seller' ? 'selected' : '' ?>>Best Selling</option>
                        <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                    </select>
                </div>
            </div>

            <!-- Products catalog wrapper (with AJAX Loading Overlay) -->
            <div class="catalog-content-wrapper">
                <div class="catalog-loading-overlay" id="catalogLoadingOverlay">
                    <div class="catalog-loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i> Filtering...
                    </div>
                </div>

                <!-- Products Grid -->
                <div class="products-grid" id="productsCatalogGrid">
                    <div id="catalogProductsContainer" style="display: contents;">
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $product): ?>
                                <?php include PUBLIC_PATH . '/components/search-result-card.php'; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="catalog-empty" style="grid-column: 1 / -1; width: 100%;">
                                <div class="catalog-empty-icon"><i class="fas fa-magnifying-glass"></i></div>
                                <h3>No Results Found</h3>
                                <p>We couldn't find any products matching "<strong><?= e($searchQuery) ?></strong>". Try adjusting your filters or checking your spelling.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="catalog-pagination" id="catalogPaginationContainer">
                <?php
                $totalPages = (int) ceil($totalProducts / $limit);
                $baseUrl = 'search.php';
                $queryParams = $_GET;
                unset($queryParams['page']);

                if ($totalPages > 1) {
                    include PUBLIC_PATH . '/components/pagination.php';
                }
                ?>
            </div>

        </main>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Initial lazy loading image trigger
    const lazyImages = [].slice.call(document.querySelectorAll("img.lazy"));
    if ("IntersectionObserver" in window) {
        let lazyImageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    let lazyImage = entry.target;
                    lazyImage.src = lazyImage.dataset.src;
                    lazyImage.classList.remove("lazy");
                    lazyImageObserver.unobserve(lazyImage);
                }
            });
        });
        lazyImages.forEach(function(lazyImage) {
            lazyImageObserver.observe(lazyImage);
        });
    } else {
        lazyImages.forEach(function(lazyImage) {
            lazyImage.src = lazyImage.dataset.src;
        });
    }
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
