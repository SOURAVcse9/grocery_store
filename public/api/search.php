<?php
/**
 * ==========================================================================
 * public/api/search.php
 * ==========================================================================
 * AJAX endpoint to fetch filtered products specifically for the search page.
 * Responds with JSON containing pre-rendered HTML chunks.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only GET allowed
require_method('GET');

// Read parameters
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

// If query is completely empty, return empty results
if (mb_strlen($searchQuery) < 2) {
    json_response(true, 'Query too short.', [
        'total_products' => 0,
        'page'           => $page,
        'limit'          => $limit,
        'offset'         => $offset,
        'html'           => '<div class="catalog-empty" style="grid-column: 1 / -1; width: 100%;"><div class="catalog-empty-icon"><i class="fas fa-search"></i></div><h3>Please enter a longer query</h3><p>Enter at least 2 characters to search for groceries.</p></div>',
        'pagination_html'=> ''
    ]);
}

try {
    $pdo = db();

    // 1. Build Query Parts dynamically
    $where = ['p.is_active = 1'];
    $params = [];

    // Search query constraint
    $where[] = '(p.name LIKE :search_term1 OR p.sku LIKE :search_term2 OR b.name LIKE :search_term3 OR c.name LIKE :search_term4)';
    $term = '%' . $searchQuery . '%';
    $params['search_term1'] = $term;
    $params['search_term2'] = $term;
    $params['search_term3'] = $term;
    $params['search_term4'] = $term;

    // Additional Filters
    if (!empty($categorySlug)) {
        $where[] = 'c.slug = :category_slug';
        $params['category_slug'] = $categorySlug;
    }

    if (!empty($brandSlug)) {
        $where[] = 'b.slug = :brand_slug';
        $params['brand_slug'] = $brandSlug;
    }

    if (is_numeric($minPrice)) {
        $where[] = 'COALESCE(p.discount_price, p.price) >= :min_price';
        $params['min_price'] = (float) $minPrice;
    }
    if (is_numeric($maxPrice)) {
        $where[] = 'COALESCE(p.discount_price, p.price) <= :max_price';
        $params['max_price'] = (float) $maxPrice;
    }

    if ($inStock) {
        $where[] = 'p.stock > 0';
    }

    if ($discounted) {
        $where[] = 'p.discount_price IS NOT NULL AND p.discount_price < p.price';
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // 2. Count Total Matching Products
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
        $params['min_rating'] = $minRating;
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
    $countStmt->execute($params);
    $totalProducts = (int) $countStmt->fetchColumn();

    // 3. Determine Order By Clause
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

    // 4. Fetch Products
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

    foreach ($params as $key => $val) {
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

    // 5. Pre-render Grid HTML using search-result-card
    ob_start();
    if (!empty($products)) {
        foreach ($products as $product) {
            include PUBLIC_PATH . '/components/search-result-card.php';
        }
    } else {
        ?>
        <div class="catalog-empty" style="grid-column: 1 / -1; width: 100%;">
            <div class="catalog-empty-icon"><i class="fas fa-magnifying-glass"></i></div>
            <h3>No Results Found</h3>
            <p>We couldn't find any products matching your search criteria. Adjust your filters or try a different term.</p>
        </div>
        <?php
    }
    $html = ob_get_clean();

    // 6. Pre-render Pagination HTML
    $totalPages = (int) ceil($totalProducts / $limit);
    $baseUrl = 'search.php';
    $queryParams = $_GET;
    unset($queryParams['page']);

    ob_start();
    if ($totalPages > 1) {
        include PUBLIC_PATH . '/components/pagination.php';
    }
    $paginationHtml = ob_get_clean();

    // Save search query to session logs for "Recent Searches" tracker
    if (empty($_SESSION['recent_searches'])) {
        $_SESSION['recent_searches'] = [];
    }
    $_SESSION['recent_searches'] = array_diff($_SESSION['recent_searches'], [$searchQuery]);
    array_unshift($_SESSION['recent_searches'], $searchQuery);
    $_SESSION['recent_searches'] = array_slice($_SESSION['recent_searches'], 0, 5); // max 5

    json_response(true, 'Search results fetched.', [
        'total_products' => $totalProducts,
        'page'           => $page,
        'limit'          => $limit,
        'offset'         => $offset,
        'html'           => $html,
        'pagination_html'=> $paginationHtml
    ]);

} catch (PDOException $e) {
    error_log('[api/search.php] Error: ' . $e->getMessage());
    json_response(false, 'An error occurred while fetching search results.', [], 500);
}
