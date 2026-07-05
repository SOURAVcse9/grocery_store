<?php
/**
 * ==========================================================================
 * public/api/products.php
 * ==========================================================================
 * API endpoint to fetch filtered products and pagination links.
 * Responds with JSON containing pre-rendered HTML chunks.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only GET allowed
require_method('GET');

// Read and sanitize input parameters
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

$limit = 9; // Number of products per page
$offset = ($page - 1) * $limit;

try {
    $pdo = db();

    // 1. Build Query Parts dynamically
    $where = ['p.is_active = 1'];
    $params = [];

    // Category filter (support single slug)
    if (!empty($categorySlug)) {
        $where[] = 'c.slug = :category_slug';
        $params['category_slug'] = $categorySlug;
    }

    // Brand filter (support single slug)
    if (!empty($brandSlug)) {
        $where[] = 'b.slug = :brand_slug';
        $params['brand_slug'] = $brandSlug;
    }

    // Price range filters
    if (is_numeric($minPrice)) {
        $where[] = 'COALESCE(p.discount_price, p.price) >= :min_price';
        $params['min_price'] = (float) $minPrice;
    }
    if (is_numeric($maxPrice)) {
        $where[] = 'COALESCE(p.discount_price, p.price) <= :max_price';
        $params['max_price'] = (float) $maxPrice;
    }

    // Availability filter
    if ($inStock) {
        $where[] = 'p.stock > 0';
    }

    // Discount filter
    if ($discounted) {
        $where[] = 'p.discount_price IS NOT NULL AND p.discount_price < p.price';
    }

    // Search query filter
    if (mb_strlen($searchQuery) >= 2) {
        $where[] = '(p.name LIKE :search_term1 OR p.sku LIKE :search_term2 OR p.description LIKE :search_term3)';
        $term = '%' . $searchQuery . '%';
        $params['search_term1'] = $term;
        $params['search_term2'] = $term;
        $params['search_term3'] = $term;
    }

    if ($minRating > 0) {
        $where[] = 'p.avg_rating >= :min_rating';
        $params['min_rating'] = $minRating;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // 2. Count Total Matching Products
    $countSql = "
        SELECT COUNT(DISTINCT p.id) 
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        {$whereClause}
    ";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalProducts = (int) $countStmt->fetchColumn();

    // 3. Construct Order By Clause based on sorting
    $orderBy = 'p.created_at DESC, p.id DESC'; // default newest
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

    // 4. Fetch Products
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

    // Bind parameters
    foreach ($params as $key => $val) {
        if (is_int($val)) {
            $stmt->bindValue($key, $val, PDO::PARAM_INT);
        } elseif (is_float($val)) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR); // PDO doesn't have float type, bind as string/number
        } else {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $products = $stmt->fetchAll();

    // 5. Pre-render Products Grid HTML
    ob_start();
    if (!empty($products)) {
        foreach ($products as $product) {
            include PUBLIC_PATH . '/components/product-card.php';
        }
    } else {
        ?>
        <div class="catalog-empty" style="grid-column: 1 / -1; width: 100%;">
            <div class="catalog-empty-icon"><i class="fas fa-search"></i></div>
            <h3>No Products Found</h3>
            <p>We couldn't find any products matching your active filters. Try adjusting your sidebar selections or query.</p>
        </div>
        <?php
    }
    $html = ob_get_clean();

    // 6. Pre-render Pagination HTML
    $totalPages = (int) ceil($totalProducts / $limit);
    $baseUrl = 'products.php';
    $queryParams = $_GET;
    unset($queryParams['page']); // Exclude page param so buildPageUrl handles it

    ob_start();
    if ($totalPages > 1) {
        include PUBLIC_PATH . '/components/pagination.php';
    }
    $paginationHtml = ob_get_clean();

    // 7. Return JSON response
    json_response(true, 'Products retrieved successfully.', [
        'total_products' => $totalProducts,
        'page'           => $page,
        'limit'          => $limit,
        'offset'         => $offset,
        'html'           => $html,
        'pagination_html'=> $paginationHtml
    ]);

} catch (PDOException $e) {
    error_log('[api/products.php] Error: ' . $e->getMessage());
    json_response(false, 'An error occurred while fetching products.', [], 500);
}
