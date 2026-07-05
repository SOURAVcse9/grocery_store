<?php
/**
 * ==========================================================================
 * public/api/reviews.php
 * ==========================================================================
 * AJAX API endpoint to fetch paginated product reviews.
 * Responds with JSON.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only GET allowed
require_method('GET');

$productId = (int) input('product_id', '0', 'get');
$sort = input('sort', 'newest', 'get');
$page = (int) input('page', '1', 'get');

if ($productId <= 0) {
    json_response(false, 'Invalid product selection.', [], 400);
}

if ($page < 1) {
    $page = 1;
}

$limit = 5; // 5 reviews per page
$offset = ($page - 1) * $limit;

try {
    $pdo = db();

    // 1. Count total reviews
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM product_reviews WHERE product_id = :pid AND status = \'approved\'');
    $countStmt->execute(['pid' => $productId]);
    $totalReviews = (int) $countStmt->fetchColumn();

    // 2. Determine sort order
    $orderBy = 'pr.created_at DESC';
    if ($sort === 'highest') {
        $orderBy = 'pr.rating DESC, pr.created_at DESC';
    } elseif ($sort === 'lowest') {
        $orderBy = 'pr.rating ASC, pr.created_at DESC';
    } elseif ($sort === 'helpful') {
        $orderBy = 'pr.helpful_count DESC, pr.created_at DESC';
    }

    // 3. Fetch reviews list (resolving verified purchase badges inline)
    $reviewsSql = "
        SELECT pr.*, u.full_name, u.avatar,
               (SELECT COUNT(*) FROM orders o 
                JOIN order_items oi ON oi.order_id = o.id 
                WHERE o.user_id = pr.user_id 
                  AND oi.product_id = pr.product_id 
                  AND o.status = 'delivered') AS verified_purchase
        FROM product_reviews pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.product_id = :pid AND pr.status = 'approved'
        ORDER BY {$orderBy}
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($reviewsSql);
    $stmt->bindValue('pid', $productId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $reviews = $stmt->fetchAll();

    $currentUserId = current_user_id();

    // 4. Pre-render Review Cards HTML
    ob_start();
    if (!empty($reviews)) {
        foreach ($reviews as $review) {
            include PUBLIC_PATH . '/components/review-card.php';
        }
    } else {
        echo '<p style="text-align:center; color:var(--color-text-muted); font-size:var(--fs-xs); padding: var(--space-4) 0; margin:0;">There are no reviews for this product yet.</p>';
    }
    $html = ob_get_clean();

    // 5. Pre-render Pagination HTML
    $totalPages = (int) ceil($totalReviews / $limit);
    $baseUrl = 'reviews.php';
    $queryParams = $_GET;
    unset($queryParams['page']);

    ob_start();
    if ($totalPages > 1) {
        include PUBLIC_PATH . '/components/pagination.php';
    }
    $paginationHtml = ob_get_clean();

    json_response(true, 'Reviews loaded.', [
        'total_reviews' => $totalReviews,
        'html'          => $html,
        'pagination_html'=> $paginationHtml
    ]);

} catch (PDOException $e) {
    error_log('[api/reviews.php] Error: ' . $e->getMessage());
    json_response(false, 'Failed to fetch reviews due to database error.', [], 500);
}
