<?php
/**
 * ==========================================================================
 * public/wishlist.php — Customer Saved Wishlist Page
 * ==========================================================================
 * Renders user's saved wishlist, support guest sessions, and triggers AJAX
 * cart additions, items removals, or total clearing.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$products = [];
$pdo = db();

try {
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

} catch (PDOException $e) {
    error_log('[wishlist.php] Error: ' . $e->getMessage());
}

// Metadata
$pageTitle = 'My Wishlist — ' . site_name();
$pageDescription = 'Manage your saved grocery items list, view current prices, and transfer items to your cart.';

$extraStylesheets = ['css/wishlist.css', 'css/cart.css'];
$extraScripts = ['js/wishlist.js'];

require_once __DIR__ . '/header.php';

// Prepare Breadcrumbs trail
$breadcrumbs = [
    ['title' => 'My Wishlist']
];
?>

<!-- Breadcrumbs -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<div class="container" style="margin-top: var(--space-5); margin-bottom: var(--space-6);">
    <div class="dashboard-card" style="padding: var(--space-5);">
        
        <!-- Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--color-border); padding-bottom:var(--space-3); margin-bottom:var(--space-4);">
            <h2 style="font-size:var(--fs-lg); font-weight:800; margin:0;"><i class="far fa-heart" style="color:var(--color-primary); margin-right:6px;"></i> My Saved Wishlist</h2>
            <?php if (!empty($products)): ?>
                <button type="button" class="btn btn-secondary btn-clear-wishlist" style="font-size:11px; font-weight:700; border-radius:var(--radius-pill); border:none; padding: 8px 16px;">
                    <i class="far fa-trash-can"></i> Clear Wishlist
                </button>
            <?php endif; ?>
        </div>

        <!-- Wishlist items rows list -->
        <div class="wishlist-list-wrapper" id="wishlistItemsContainer">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                    <?php include PUBLIC_PATH . '/components/wishlist-card.php'; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="cart-empty-page" style="box-shadow:none; border:none; background:transparent;">
                    <div class="cart-empty-icon" style="color:var(--color-text-faint);"><i class="far fa-heart"></i></div>
                    <h2>Your Wishlist is Empty</h2>
                    <p>Save items you like to buy later. Click the heart icon on any product page!</p>
                    <a href="<?= url_for('products.php') ?>" class="btn btn-primary">Discover Products</a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
