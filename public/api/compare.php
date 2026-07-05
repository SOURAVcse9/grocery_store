<?php
/**
 * ==========================================================================
 * public/api/compare.php
 * ==========================================================================
 * AJAX API endpoint to fetch pre-rendered comparison grid matrix.
 * Responds with JSON.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only GET allowed
require_method('GET');

try {
    $pdo = db();
    $products = [];

    $userId = current_user_id();
    $sqlSelector = '';
    $selectorVal = null;

    if ($userId !== null) {
        $sqlSelector = 'ci.user_id = :uid';
        $selectorVal = $userId;
    } else {
        $sqlSelector = 'ci.session_id = :sid';
        $selectorVal = get_or_create_guest_token();
    }

    // Fetch Compared Products details
    $compareSql = "
        SELECT p.*, c.name AS category_name, b.name AS brand_name,
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count
        FROM compare_items ci
        JOIN products p ON p.id = ci.product_id
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE {$sqlSelector} AND p.is_active = 1
        GROUP BY p.id
        ORDER BY ci.created_at DESC
    ";

    $stmt = $pdo->prepare($compareSql);
    $stmt->execute([($userId !== null ? 'uid' : 'sid') => $selectorVal]);
    $products = $stmt->fetchAll();

    $count = count($products);

    // Pre-render HTML
    ob_start();
    include PUBLIC_PATH . '/components/compare-table.php';
    $html = ob_get_clean();

    json_response(true, 'Comparison list retrieved.', [
        'compare_count' => $count,
        'html'          => $html
    ]);

} catch (PDOException $e) {
    error_log('[api/compare.php] Error: ' . $e->getMessage());
    json_response(false, 'Database load failed.', [], 500);
}
