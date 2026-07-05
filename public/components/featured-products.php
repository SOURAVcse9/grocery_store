<?php
/**
 * ==========================================================================
 * public/components/featured-products.php
 * ==========================================================================
 * Featured Products Catalog Grid block.
 * Renders products matching is_featured = 1 using the product-card component.
 * ==========================================================================
 */

declare(strict_types=1);

try {
    $pdo = db();
    
    // Fetch featured items
    $stmt = $pdo->query("
        SELECT p.*, c.name AS category_name, b.name AS brand_name,
               COALESCE(AVG(pr.rating), 0) AS avg_rating,
               COUNT(pr.id) AS review_count
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN brands b ON b.id = p.brand_id
        LEFT JOIN product_reviews pr ON pr.product_id = p.id
        WHERE p.is_active = 1 AND p.is_featured = 1
        GROUP BY p.id
        ORDER BY p.id DESC
        LIMIT 8
    ");
    $featuredProducts = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[components/featured-products.php] Query fail: ' . $e->getMessage());
    $featuredProducts = [];
}

// Skip rendering if no products are flagged as featured
if (empty($featuredProducts)) {
    return;
}
?>

<section class="products-section featured-products-section" style="margin-bottom:var(--space-5);">
    <div class="category-section-header">
        <div class="section-title-group">
            <h2>Featured Products</h2>
            <p>Our handpicked recommendations of fresh organic items</p>
        </div>
        <a href="<?= url_for('products.php') ?>" class="view-all-link">
            Shop All <i class="fas fa-chevron-right"></i>
        </a>
    </div>

    <!-- Product Grid -->
    <div class="products-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));">
        <?php foreach ($featuredProducts as $product): ?>
            <?php include PUBLIC_PATH . '/components/product-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
