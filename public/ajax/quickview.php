<?php
/**
 * ==========================================================================
 * public/ajax/quickview.php
 * ==========================================================================
 * AJAX endpoint to fetch quick view modal content.
 * Responds with JSON containing pre-rendered HTML of the modal body.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../dbconnect.php';

// Only GET allowed for fetching details
require_method('GET');

$productId = (int) input('product_id', '0', 'get');

if ($productId <= 0) {
    json_response(false, 'Invalid product selection.', [], 400);
}

try {
    $pdo = db();

    // Query product details with category, brand, rating and review count
    $stmt = $pdo->prepare('
        SELECT p.*, 
               c.name AS category_name, 
               c.slug AS category_slug, 
               b.name AS brand_name,
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE p.id = :id AND p.is_active = 1
        GROUP BY p.id
        LIMIT 1
    ');
    $stmt->execute(['id' => $productId]);
    $product = $stmt->fetch();

    if (!$product) {
        json_response(false, 'Product not found or unavailable.', [], 404);
    }

    // Fetch product gallery images
    $imgStmt = $pdo->prepare('SELECT image_url FROM product_images WHERE product_id = :id ORDER BY sort_order ASC');
    $imgStmt->execute(['id' => $productId]);
    $gallery = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

    // If gallery is empty, fall back to the thumbnail
    if (empty($gallery) && !empty($product['thumbnail'])) {
        $gallery[] = $product['thumbnail'];
    }

    // Render the modal-quickview component into an output buffer
    ob_start();
    include PUBLIC_PATH . '/components/modal-quickview.php';
    $html = ob_get_clean();

    json_response(true, 'Product details loaded.', [
        'html' => $html
    ]);

} catch (PDOException $e) {
    error_log('[quickview.php] Error: ' . $e->getMessage());
    json_response(false, 'An error occurred while loading details.', [], 500);
}
