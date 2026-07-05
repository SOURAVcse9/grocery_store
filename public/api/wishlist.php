<?php
/**
 * ==========================================================================
 * public/api/wishlist.php
 * ==========================================================================
 * AJAX API endpoint to fetch pre-rendered wishlist item lists.
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

    if (is_logged_in()) {
        $userId = current_user_id();
        
        $stmt = $pdo->prepare('
            SELECT p.*, c.name AS category_name, b.name AS brand_name
            FROM wishlists w
            JOIN products p ON p.id = w.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN brands b ON b.id = p.brand_id
            WHERE w.user_id = :uid AND p.is_active = 1
            ORDER BY w.created_at DESC
        ');
        $stmt->execute(['uid' => $userId]);
        $products = $stmt->fetchAll();
    } else {
        // Guest session wishlist
        if (!empty($_SESSION['wishlist'])) {
            $productIds = array_keys($_SESSION['wishlist']);
            $inClause = implode(',', array_map('intval', $productIds));

            $stmt = $pdo->query("
                SELECT p.*, c.name AS category_name, b.name AS brand_name
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN brands b ON b.id = p.brand_id
                WHERE p.id IN ({$inClause}) AND p.is_active = 1
                ORDER BY p.name ASC
            ");
            $products = $stmt->fetchAll();
        }
    }

    $count = count($products);

    // Pre-render HTML
    ob_start();
    if (!empty($products)) {
        foreach ($products as $product) {
            include PUBLIC_PATH . '/components/wishlist-card.php';
        }
    } else {
        ?>
        <div class="cart-empty-page" style="box-shadow:none; border:none; background:transparent;">
            <div class="cart-empty-icon" style="color:var(--color-text-faint);"><i class="far fa-heart"></i></div>
            <h2>Your Wishlist is Empty</h2>
            <p>Save items you like to buy later. Click the heart icon on any product page!</p>
            <a href="<?= url_for('products.php') ?>" class="btn btn-primary">Discover Products</a>
        </div>
        <?php
    }
    $html = ob_get_clean();

    json_response(true, 'Wishlist items retrieved.', [
        'wishlist_count' => $count,
        'html'           => $html
    ]);

} catch (PDOException $e) {
    error_log('[api/wishlist.php] Error: ' . $e->getMessage());
    json_response(false, 'Database load failed.', [], 500);
}
