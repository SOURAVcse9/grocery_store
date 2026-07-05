<?php
/**
 * ==========================================================================
 * public/orders.php — Customer Purchase History Page
 * ==========================================================================
 * Lists past orders, filters by status, searches by order number, and
 * processes reorder triggers.
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';

// Secure page access
require_login();

$user = current_user();
$userId = (int) $user['id'];
$pdo = db();

// --------------------------------------------------------------------------
// 1. AJAX Reorder POST Action Processor
// --------------------------------------------------------------------------
if (method_is('post') && input('action', '') === 'reorder') {
    verify_csrf_or_fail(true);

    $orderId = (int) input('order_id', '0');

    if ($orderId <= 0) {
        json_response(false, 'Invalid order selection.', [], 400);
    }

    try {
        // Verify order ownership
        $orderStmt = $pdo->prepare('SELECT id FROM orders WHERE id = :id AND user_id = :uid LIMIT 1');
        $orderStmt->execute(['id' => $orderId, 'uid' => $userId]);
        if (!$orderStmt->fetch()) {
            json_response(false, 'Order not found or unauthorized.', [], 403);
        }

        // Fetch items from this order, joining products to check availability and stock
        $itemsStmt = $pdo->prepare('
            SELECT oi.product_id, oi.quantity, p.price, p.stock, p.is_active, p.name
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = :oid
        ');
        $itemsStmt->execute(['oid' => $orderId]);
        $pastItems = $itemsStmt->fetchAll();

        if (empty($pastItems)) {
            json_response(false, 'No valid items found in this order to reorder.', [], 422);
        }

        $cartId = current_cart_id();
        $addedCount = 0;

        $upsertStmt = $pdo->prepare('
            INSERT INTO cart_items (cart_id, product_id, quantity, price, created_at, updated_at)
            VALUES (:cart_id, :product_id, :qty, :price, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
                quantity = LEAST(quantity + VALUES(quantity), :max_stock),
                updated_at = NOW()
        ');

        foreach ($pastItems as $item) {
            // Skip inactive or completely out-of-stock items
            if ((int) $item['is_active'] === 0 || (int) $item['stock'] <= 0) {
                continue;
            }

            $qtyToAdd = min((int) $item['quantity'], (int) $item['stock']);
            
            $upsertStmt->bindValue('cart_id', $cartId, PDO::PARAM_INT);
            $upsertStmt->bindValue('product_id', (int) $item['product_id'], PDO::PARAM_INT);
            $upsertStmt->bindValue('qty', $qtyToAdd, PDO::PARAM_INT);
            $upsertStmt->bindValue('price', (float) $item['price'], PDO::PARAM_STR);
            $upsertStmt->bindValue('max_stock', (int) $item['stock'], PDO::PARAM_INT);
            $upsertStmt->execute();

            $addedCount++;
        }

        if ($addedCount === 0) {
            json_response(false, 'Reorder failed. All products in this order are currently out of stock or unavailable.', [], 422);
        }

        // Get new cart count
        $cartCount = cart_item_count();

        json_response(true, "Successfully added {$addedCount} items to your cart!", [
            'cart_count' => $cartCount
        ]);

    } catch (PDOException $e) {
        error_log('[orders.php] Reorder failed: ' . $e->getMessage());
        json_response(false, 'Failed to reorder items due to database error.', [], 500);
    }
}

// --------------------------------------------------------------------------
// 2. Fetch User Orders History (with Search, Filter, Sort, Pagination)
// --------------------------------------------------------------------------
$searchQuery = trim(input('q', '', 'get'));
$statusFilter = trim(input('status', '', 'get'));
$sortFilter = trim(input('sort', 'newest', 'get'));
$page = (int) input('page', '1', 'get');

if ($page < 1) {
    $page = 1;
}

$limit = 5; // Orders per page
$offset = ($page - 1) * $limit;

try {
    $where = ['user_id = :uid'];
    $queryParams = ['uid' => $userId];

    // Search query filter (matches order number)
    if (!empty($searchQuery)) {
        $where[] = 'order_number LIKE :search';
        $queryParams['search'] = '%' . $searchQuery . '%';
    }

    // Status filter
    if (!empty($statusFilter)) {
        $where[] = 'status = :status';
        $queryParams['status'] = $statusFilter;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Sorting order
    $orderBy = 'created_at DESC';
    if ($sortFilter === 'oldest') {
        $orderBy = 'created_at ASC';
    } elseif ($sortFilter === 'total_asc') {
        $orderBy = 'total_amount ASC';
    } elseif ($sortFilter === 'total_desc') {
        $orderBy = 'total_amount DESC';
    }

    // Count Total Orders matching
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders {$whereClause}");
    $countStmt->execute($queryParams);
    $totalOrders = (int) $countStmt->fetchColumn();

    // Fetch paginated Orders
    $ordersSql = "
        SELECT id, order_number, total_amount, payment_method, status, created_at
        FROM orders 
        {$whereClause} 
        ORDER BY {$orderBy} 
        LIMIT :limit OFFSET :offset
    ";
    $ordersStmt = $pdo->prepare($ordersSql);
    
    foreach ($queryParams as $key => $val) {
        $ordersStmt->bindValue($key, $val);
    }
    $ordersStmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $ordersStmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $ordersStmt->execute();
    $orders = $ordersStmt->fetchAll();

    // optimized query: fetch all order items for the active page in a single query
    $orderItems = [];
    if (!empty($orders)) {
        $orderIds = array_column($orders, 'id');
        $inClause = implode(',', array_map('intval', $orderIds));

        $itemsQuery = $pdo->query("
            SELECT oi.order_id, oi.product_name, oi.quantity, p.thumbnail, p.slug
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id IN ({$inClause})
            ORDER BY oi.id ASC
        ");

        foreach ($itemsQuery->fetchAll() as $row) {
            $orderItems[$row['order_id']][] = $row;
        }
    }

} catch (PDOException $e) {
    error_log('[orders.php] Load error: ' . $e->getMessage());
    if (APP_DEBUG) {
        die('Orders Database Error: ' . htmlspecialchars($e->getMessage()));
    }
}

// Meta configurations
$pageTitle = 'My Purchase History — ' . site_name();
$pageDescription = 'Track shipped packages, print order receipts, and reorder fresh groceries.';

$extraStylesheets = ['css/account.css', 'css/orders.css'];
$extraScripts = ['js/orders.js'];

require_once __DIR__ . '/header.php';

// Prepare Breadcrumbs trail
$breadcrumbs = [
    ['title' => 'My Dashboard', 'link' => 'account.php'],
    ['title' => 'Order History']
];
?>

<!-- Breadcrumbs -->
<?php include PUBLIC_PATH . '/components/breadcrumb.php'; ?>

<div class="container" style="margin-top: var(--space-5);">
    <div class="account-layout">
        
        <!-- Left Sidebar Navigation Menu -->
        <aside class="account-sidebar">
            <ul class="account-menu-list">
                <li class="account-menu-item">
                    <a href="<?= url_for('account.php') ?>"><i class="fas fa-gauge"></i> Dashboard</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('profile.php') ?>"><i class="fas fa-user-gear"></i> Edit Profile</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('addresses.php') ?>"><i class="fas fa-map-location-dot"></i> Saved Addresses</a>
                </li>
                <li class="account-menu-item active">
                    <a href="<?= url_for('orders.php') ?>"><i class="fas fa-box-open"></i> My Orders</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('analytics.php') ?>"><i class="fas fa-chart-line"></i> Analytics</a>
                </li>
                <li class="account-menu-item">
                    <a href="<?= url_for('logout.php') ?>" style="color:var(--color-danger);"><i class="fas fa-power-off" style="color:var(--color-danger);"></i> Logout</a>
                </li>
            </ul>
        </aside>

        <!-- Right Main Panel -->
        <main class="account-main-content">
            
            <div class="dashboard-card" style="margin-bottom: var(--space-4);">
                <h3 class="dashboard-card-title" style="border-bottom:none; margin-bottom:0;">Order History</h3>
                
                <!-- Search and Filters Form -->
                <form action="<?= url_for('orders.php') ?>" method="get" style="display:flex; flex-wrap:wrap; gap:var(--space-3); margin-top:var(--space-4); margin-bottom:var(--space-4);">
                    <div style="flex-grow:1; display:flex; gap:6px;">
                        <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Search by Order #..." class="coupon-input" style="font-size:12px; border-radius:var(--radius-pill); padding:8px 16px; width:100%;" aria-label="Search Orders">
                        <button type="submit" class="btn btn-primary" style="font-size:11px; padding:6px 18px; border-radius:var(--radius-pill); border:none;"><i class="fas fa-search"></i> Search</button>
                    </div>

                    <div style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
                        <select name="status" class="sort-select" style="font-size:11px; padding:6px 12px;" onchange="this.form.submit();" aria-label="Filter status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="shipped" <?= $statusFilter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>

                        <select name="sort" class="sort-select" style="font-size:11px; padding:6px 12px;" onchange="this.form.submit();" aria-label="Sort orders">
                            <option value="newest" <?= $sortFilter === 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="oldest" <?= $sortFilter === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="total_desc" <?= $sortFilter === 'total_desc' ? 'selected' : '' ?>>Total: High to Low</option>
                            <option value="total_asc" <?= $sortFilter === 'total_asc' ? 'selected' : '' ?>>Total: Low to High</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- List of Order Cards -->
            <div class="orders-list-wrapper">
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <?php include PUBLIC_PATH . '/components/order-card.php'; ?>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <div style="display:flex; justify-content:center; margin-top:var(--space-5);">
                        <?php 
                        $totalPages = (int) ceil($totalOrders / $limit);
                        $baseUrl = 'orders.php';
                        $queryParams = $_GET;
                        unset($queryParams['page']);

                        if ($totalPages > 1) {
                            include PUBLIC_PATH . '/components/pagination.php';
                        }
                        ?>
                    </div>

                <?php else: ?>
                    <div class="cart-empty-page" style="padding: var(--space-6) var(--space-4);">
                        <div class="cart-empty-icon"><i class="fas fa-box-open"></i></div>
                        <h2>No Orders Found</h2>
                        <p>We couldn't find any orders matching your active search/filter. Go ahead and place some grocery orders!</p>
                        <a href="<?= url_for('products.php') ?>" class="btn btn-primary">Browse Shop</a>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
