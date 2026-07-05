<?php
/**
 * ==========================================================================
 * public/compare.php — Product Comparison Sheet Page
 * ==========================================================================
 * Renders side-by-side attributes matrix, updates values via AJAX, and
 * transfers items to carts.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

$products = [];
$pdo = db();

try {
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

    // SSR fetch compared items
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

} catch (PDOException $e) {
    error_log('[compare.php] SSR load failed: ' . $e->getMessage());
}

// Layout metadata
$pageTitle = 'Compare Products — ' . site_name();
$pageDescription = 'Compare prices, weights, brands, and ratings of different fresh groceries side-by-side.';

$extraStylesheets = ['css/compare.css', 'css/cart.css'];
$extraScripts = ['js/compare.js'];

require_once __DIR__ . '/header.php';

// Prepare Breadcrumbs trail
$breadcrumbs = [
    ['title' => 'Product Comparison']
];
?>

<!-- Breadcrumbs -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<div class="container" style="margin-top: var(--space-5); margin-bottom: var(--space-6);">
    <div class="dashboard-card" style="padding: var(--space-5);">
        
        <!-- Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--color-border); padding-bottom:var(--space-3); margin-bottom:var(--space-4);">
            <h2 style="font-size:var(--fs-lg); font-weight:800; margin:0;"><i class="fas fa-arrows-left-right" style="color:var(--color-primary); margin-right:6px;"></i> Product Comparison Matrix</h2>
            <?php if (!empty($products)): ?>
                <button type="button" class="btn btn-secondary btn-clear-compare" style="font-size:11px; font-weight:700; border-radius:var(--radius-pill); border:none; padding: 8px 16px;">
                    <i class="far fa-trash-can"></i> Clear Comparison
                </button>
            <?php endif; ?>
        </div>

        <!-- Matrix table container -->
        <div id="compareTableContainer">
            <?php include PUBLIC_PATH . '/components/compare-table.php'; ?>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
