<?php
/**
 * ==========================================================================
 * public/components/recently-viewed.php
 * ==========================================================================
 * Recently Viewed Products grid section.
 * Queries product IDs tracked in session, maintaining chronological order.
 * ==========================================================================
 */

declare(strict_types=1);

if (empty($_SESSION['recently_viewed']) || !is_array($_SESSION['recently_viewed'])) {
    return;
}

$viewedIds = array_filter(array_map('intval', $_SESSION['recently_viewed']));

// Skip if array is empty
if (empty($viewedIds)) {
    return;
}

try {
    $pdo = db();
    $inClause = implode(',', $viewedIds);
    
    // Query recently viewed items, preserving chronological order via FIELD()
    $stmt = $pdo->query("
        SELECT p.*, c.name AS category_name, b.name AS brand_name,
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE p.id IN ({$inClause}) AND p.is_active = 1
        GROUP BY p.id
        ORDER BY FIELD(p.id, {$inClause})
        LIMIT 5
    ");
    $recentProducts = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[components/recently-viewed.php] Query fail: ' . $e->getMessage());
    $recentProducts = [];
}

if (empty($recentProducts)) {
    return;
}
?>

<section class="products-section recently-viewed-section" style="margin-top:var(--space-5); margin-bottom:var(--space-5);">
    <div class="category-section-header">
        <div class="section-title-group">
            <h2>Recently Viewed Products</h2>
            <p>Pick up where you left off with these items</p>
        </div>
    </div>

    <!-- Product Grid -->
    <div class="products-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
        <?php foreach ($recentProducts as $product): ?>
            <?php include PUBLIC_PATH . '/components/product-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
