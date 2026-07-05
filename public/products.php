<?php
/**
 * ==========================================================================
 * public/products.php — Catalog Listing & Filters Page
 * ==========================================================================
 * Displays product listing grids, category sidebars, brand checklists,
 * rating filters, and price ranges. Supports hybrid SSR + AJAX loading.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Retrieve active filters for initial load SSR
$categorySlug = input('category', '', 'get');
$brandSlug = input('brand', '', 'get');
$minPrice = input('min_price', '', 'get');
$maxPrice = input('max_price', '', 'get');
$inStock = input('availability', '', 'get') === 'in_stock';
$minRating = (int) input('rating', '0', 'get');
$discounted = input('discount', '', 'get') === 'on_sale';
$sort = input('sort', 'newest', 'get');
$searchQuery = trim(input('q', '', 'get'));
$page = (int) input('page', '1', 'get');

if ($page < 1) {
    $page = 1;
}

$limit = 9;
$offset = ($page - 1) * $limit;

try {
    $pdo = db();

    // 1. Fetch filter metadata for Sidebar Checklists
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

    // 2. Build Query scope for active filters
    $where = ['p.is_active = 1'];
    $queryParams = [];

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

    if (mb_strlen($searchQuery) >= 2) {
        $where[] = '(p.name LIKE :search_term1 OR p.sku LIKE :search_term2 OR p.description LIKE :search_term3)';
        $term = '%' . $searchQuery . '%';
        $queryParams['search_term1'] = $term;
        $queryParams['search_term2'] = $term;
        $queryParams['search_term3'] = $term;
    }

    if ($minRating > 0) {
        $where[] = 'p.avg_rating >= :min_rating';
        $queryParams['min_rating'] = $minRating;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // 3. Count matching products
    $countSql = "
        SELECT COUNT(DISTINCT p.id) 
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        {$whereClause}
    ";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($queryParams);
    $totalProducts = (int) $countStmt->fetchColumn();

    // 4. Determine sorting order clause
    $orderBy = 'p.created_at DESC, p.id DESC';
    if ($sort === 'price_asc') {
        $orderBy = 'COALESCE(p.discount_price, p.price) ASC, p.id DESC';
    } elseif ($sort === 'price_desc') {
        $orderBy = 'COALESCE(p.discount_price, p.price) DESC, p.id DESC';
    } elseif ($sort === 'popular') {
        $orderBy = 'p.review_count DESC, p.id DESC';
    } elseif ($sort === 'best_seller') {
        $orderBy = 'sales_count DESC, p.id DESC';
    } elseif ($sort === 'rating') {
        $orderBy = 'p.avg_rating DESC, p.review_count DESC, p.id DESC';
    }

    // 5. Query Products
    $productsSql = "
        SELECT p.*,
               c.name AS category_name,
               c.slug AS category_slug,
               b.name AS brand_name,
               (SELECT COUNT(*) FROM order_items WHERE product_id = p.id) AS sales_count
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        {$whereClause}
        ORDER BY {$orderBy} 
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($productsSql);

    foreach ($queryParams as $key => $val) {
        if (is_int($val)) {
            $stmt->bindValue($key, $val, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();

    // 6. Build Breadcrumb links
    $breadcrumbs = [
        ['title' => t('shop') ?? 'Shop', 'link' => 'products.php']
    ];

    $bannerTitle = 'Store Catalog';
    $bannerText = 'Browse through our premium selection of grocery essentials';

    if (!empty($categorySlug)) {
        // Find category name for title & breadcrumb
        $catNameQuery = $pdo->prepare('SELECT name FROM categories WHERE slug = :slug LIMIT 1');
        $catNameQuery->execute(['slug' => $categorySlug]);
        $catName = $catNameQuery->fetchColumn();
        if ($catName !== false) {
            $breadcrumbs[] = ['title' => $catName];
            $bannerTitle = $catName;
            $bannerText = 'Fresh and high quality products listed under ' . $catName;
        }
    } elseif (!empty($brandSlug)) {
        // Find brand name
        $brandNameQuery = $pdo->prepare('SELECT name FROM brands WHERE slug = :slug LIMIT 1');
        $brandNameQuery->execute(['slug' => $brandSlug]);
        $brandName = $brandNameQuery->fetchColumn();
        if ($brandName !== false) {
            $breadcrumbs[] = ['title' => $brandName];
            $bannerTitle = $brandName;
            $bannerText = 'Premium products manufactured by ' . $brandName;
        }
    } elseif (!empty($searchQuery)) {
        $breadcrumbs[] = ['title' => 'Search: "' . $searchQuery . '"'];
        $bannerTitle = 'Search Results';
        $bannerText = 'Showing products matching your search query: "' . $searchQuery . '"';
    }

} catch (PDOException $e) {
    error_log('[products.php] Database load error: ' . $e->getMessage());
    if (APP_DEBUG) {
        die('Catalog Loading Error: ' . htmlspecialchars($e->getMessage()));
    }
}

// Page variables for layout
$pageTitle = $bannerTitle . ' — ' . site_name();
$pageDescription = $bannerText;

$extraStylesheets = ['css/home.css', 'css/products.css'];
$extraScripts = [
    'js/quickview.js',
    'js/search.js',
    'js/cart.js',
    'js/wishlist.js',
    'js/compare.js'
];

require_once __DIR__ . '/header.php';
?>

<!-- Breadcrumbs -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<div class="container" id="catalogWrapper">
    <!-- Catalog Hero Banner -->
    <div class="catalog-banner">
        <h1><?= e($bannerTitle) ?></h1>
        <p><?= e($bannerText) ?></p>
    </div>

    <!-- Catalog Core Layout -->
    <div class="catalog-layout">
        
        <!-- Left: Filters Sidebar Panel -->
        <?php include PUBLIC_PATH . '/components/filter-sidebar.php'; ?>

        <!-- Right: Catalog Main Area -->
        <main class="catalog-main-content">
            <!-- Catalog Toolbar -->
            <div class="catalog-toolbar">
                <div class="toolbar-left">
                    <button type="button" class="btn-mobile-filter" id="btnMobileFilter">
                        <i class="fas fa-filter"></i> Filters
                    </button>
                    
                    <span class="product-count-label" id="productCountLabel">
                        <?php
                        $start = $totalProducts === 0 ? 0 : $offset + 1;
                        $end = min($offset + $limit, $totalProducts);
                        echo "Showing {$start}-{$end} of {$totalProducts} products";
                        ?>
                    </span>

                    <!-- Grid / List Toggles -->
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

            <!-- Products Catalog Wrapper (with AJAX Loading Overlay) -->
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
                                <?php include PUBLIC_PATH . '/components/product-card.php'; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="catalog-empty" style="grid-column: 1 / -1; width: 100%;">
                                <div class="catalog-empty-icon"><i class="fas fa-search"></i></div>
                                <h3>No Products Found</h3>
                                <p>We couldn't find any products matching your active filters. Try adjusting your sidebar selections or search query.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Pagination Container -->
            <div class="catalog-pagination" id="catalogPaginationContainer">
                <?php
                $totalPages = (int) ceil($totalProducts / $limit);
                $baseUrl = 'products.php';
                $queryParams = $_GET;
                unset($queryParams['page']); // Exclude page param so buildPageUrl handles it

                if ($totalPages > 1) {
                    include PUBLIC_PATH . '/components/pagination.php';
                }
                ?>
            </div>
        </main>
    </div>
</div>

<script>
// Initial Lazy Loading Setup
document.addEventListener("DOMContentLoaded", function() {
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
