<?php
/**
 * ==========================================================================
 * admin/customers/view.php — Customer Profile Dashboard
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_permission('customers.view');

$pdo = db();
$userId = (int) input('id', '0', 'get');

if ($userId <= 0) {
    header('Location: index.php');
    exit;
}

$error = null;
$success = null;

try {
    // 1. Fetch user core info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role_id != 1 LIMIT 1");
    $stmt->execute(['id' => $userId]);
    $u = $stmt->fetch();

    if (!$u) {
        flash('cust_msg', 'Customer not found.', 'error');
        header('Location: index.php');
        exit;
    }

    // 2. Handle POST admin controls
    if (method_is('post')) {
        verify_csrf_or_fail();
        $action = input('admin_action', '');

        if ($action === 'reset_password') {
            require_admin_permission('customers.edit');
            $newPassword = trim(input('new_password', ''));
            if (strlen($newPassword) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } else {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ?, last_password_change = NOW() WHERE id = ?")->execute([$hash, $userId]);
                $pdo->prepare("INSERT INTO customer_security_logs (user_id, event_type, description, ip_address) VALUES (?, 'password_change', 'Password reset by administrative force.', ?)")->execute([$userId, $_SERVER['REMOTE_ADDR']]);
                $success = 'Password successfully reset!';
            }
        } elseif ($action === 'force_logout') {
            require_admin_permission('customers.edit');
            // Force logout by deleting active remember me tokens
            $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?")->execute([$userId]);
            $pdo->prepare("INSERT INTO customer_security_logs (user_id, event_type, description, ip_address) VALUES (?, 'force_logout', 'Session forced logout by store administrator.', ?)")->execute([$userId, $_SERVER['REMOTE_ADDR']]);
            $success = 'User sessions successfully terminated.';
        } elseif ($action === 'toggle_ban') {
            require_admin_permission('customers.ban');
            $banVal = (int) input('ban_val', '0');
            $reason = trim(input('ban_reason', 'Administrative action'));
            
            $pdo->prepare("UPDATE users SET is_banned = ?, ban_reason = ?, is_active = ? WHERE id = ?")->execute([
                $banVal,
                $banVal ? $reason : null,
                $banVal ? 0 : 1,
                $userId
            ]);

            $event = $banVal ? 'ban' : 'unban';
            $desc = $banVal ? "Banned. Reason: {$reason}" : 'Ban lifted.';
            $pdo->prepare("INSERT INTO customer_security_logs (user_id, event_type, description, ip_address) VALUES (?, ?, ?, ?)")->execute([$userId, $event, $desc, $_SERVER['REMOTE_ADDR']]);
            
            $u['is_banned'] = $banVal;
            $u['ban_reason'] = $banVal ? $reason : null;
            $success = $banVal ? 'Customer account banned.' : 'Customer account ban lifted.';
        } elseif ($action === 'send_email') {
            // Simulated Email sender logger
            $emailType = input('email_type', 'welcome');
            $subject = trim(input('email_subject', ''));
            $body = trim(input('email_body', ''));

            if (empty($subject) || empty($body)) {
                $error = 'Email subject and content body are required.';
            } else {
                $pdo->prepare("INSERT INTO customer_activity_logs (user_id, activity_type, description, ip_address) VALUES (?, 'email_sent', ?, ?)")->execute([
                    $userId,
                    "Sent Email ({$emailType}): '{$subject}'",
                    $_SERVER['REMOTE_ADDR']
                ]);
                $success = "Email notification successfully logged/sent to {$u['email']}.";
            }
        } elseif ($action === 'send_notification') {
            require_admin_permission('customers.notifications');
            $title = trim(input('notif_title', ''));
            $msg = trim(input('notif_msg', ''));
            $type = input('notif_type', 'account');

            if (empty($title) || empty($msg)) {
                $error = 'Notification title and details are required.';
            } else {
                $pdo->prepare("INSERT INTO customer_notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)")->execute([
                    $userId,
                    $title,
                    $msg,
                    $type
                ]);
                $success = 'In-app notification dispatched successfully.';
            }
        } elseif ($action === 'delete_review') {
            require_admin_permission('reviews.manage');
            $revId = (int) input('review_id', '0');
            if ($revId > 0) {
                $stmt = $pdo->prepare("SELECT product_id FROM product_reviews WHERE id = ? LIMIT 1");
                $stmt->execute([$revId]);
                $productId = (int) $stmt->fetchColumn();
                
                $pdo->prepare("DELETE FROM product_reviews WHERE id = ? AND user_id = ?")->execute([$revId, $userId]);
                
                if ($productId > 0) {
                    $upd = $pdo->prepare("
                        UPDATE products SET 
                            avg_rating = COALESCE((SELECT ROUND(AVG(rating), 2) FROM product_reviews WHERE product_id = :pid AND status = \'approved\'), 0.00),
                            review_count = (SELECT COUNT(*) FROM product_reviews WHERE product_id = :pid2 AND status = \'approved\')
                        WHERE id = :pid3
                    ");
                    $upd->execute([
                        'pid' => $productId,
                        'pid2' => $productId,
                        'pid3' => $productId
                    ]);
                }
                $success = 'Product review deleted successfully.';
            }
        } elseif ($action === 'remove_wishlist') {
            $prodId = (int) input('product_id', '0');
            if ($prodId > 0) {
                $pdo->prepare("DELETE FROM wishlists WHERE product_id = ? AND user_id = ?")->execute([$prodId, $userId]);
                $success = 'Product removed from customer wishlist.';
            }
        }
    }

    // 3. Fetch related customer aggregates & history logs
    // Addresses
    $addresses = $pdo->prepare("SELECT * FROM addresses WHERE user_id = :id");
    $addresses->execute(['id' => $userId]);
    $addressBook = $addresses->fetchAll();

    // Default address lookup
    $defaultAddr = null;
    foreach ($addressBook as $addr) {
        if ($addr['is_default'] ?? false) {
            $defaultAddr = $addr;
            break;
        }
    }

    // Orders history list
    $ordersStmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = :id ORDER BY created_at DESC");
    $ordersStmt->execute(['id' => $userId]);
    $orders = $ordersStmt->fetchAll();

    // Wishlist items
    $wishlist = $pdo->prepare("
        SELECT w.product_id, w.created_at, p.name, p.price, p.thumbnail AS image 
        FROM wishlists w
        JOIN products p ON p.id = w.product_id
        WHERE w.user_id = :id
    ");
    $wishlist->execute(['id' => $userId]);
    $wishlistItems = $wishlist->fetchAll();

    // Cart items current details
    $cartItems = $pdo->prepare("
        SELECT ci.quantity, ci.created_at, p.name, p.price, p.thumbnail AS image
        FROM cart_items ci
        JOIN carts c ON c.id = ci.cart_id
        JOIN products p ON p.id = ci.product_id
        WHERE c.user_id = :id
    ");
    $cartItems->execute(['id' => $userId]);
    $cart = $cartItems->fetchAll();

    $cartTotal = 0;
    foreach ($cart as $item) {
        $cartTotal += (float)$item['price'] * (int)$item['quantity'];
    }

    // Reviews list
    $reviewsStmt = $pdo->prepare("
        SELECT pr.*, p.name AS product_name 
        FROM product_reviews pr
        JOIN products p ON p.id = pr.product_id
        WHERE pr.user_id = :id
        ORDER BY pr.created_at DESC
    ");
    $reviewsStmt->execute(['id' => $userId]);
    $reviews = $reviewsStmt->fetchAll();

    // Analytics details
    $stats = $pdo->prepare("
        SELECT 
            COUNT(o.id) AS total_orders,
            COALESCE(SUM(o.total_amount), 0) AS total_spent,
            COALESCE(AVG(o.total_amount), 0) AS aov,
            (SELECT COUNT(*) FROM product_reviews WHERE user_id = :id1) AS reviews_count,
            (SELECT COUNT(*) FROM wishlists WHERE user_id = :id2) AS wishlist_count
        FROM orders o
        WHERE o.user_id = :id3 AND o.status = 'delivered'
    ");
    $stats->execute([
        'id1' => $userId,
        'id2' => $userId,
        'id3' => $userId
    ]);
    $analytics = $stats->fetch();

    // Activity timeline log
    $actLogs = $pdo->prepare("SELECT * FROM customer_activity_logs WHERE user_id = :id ORDER BY created_at DESC LIMIT 15");
    $actLogs->execute(['id' => $userId]);
    $activities = $actLogs->fetchAll();

    // Security logs details
    $secLogs = $pdo->prepare("SELECT * FROM customer_security_logs WHERE user_id = :id ORDER BY created_at DESC LIMIT 15");
    $secLogs->execute(['id' => $userId]);
    $securityEvents = $secLogs->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/customers/view] failed aggregates load: ' . $e->getMessage());
    $error = 'Database connection error while loading customer profile aggregates.';
}
$pageTitle = 'Customer Profile — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Customer Profile</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">ID: <strong>#<?= $u['id'] ?></strong> | Username: <strong><?= e($u['username'] ?? 'N/A') ?></strong> | Registered: <?= date('M d, Y', strtotime($u['created_at'])) ?></p>
    </div>
    <div style="display:flex; gap:8px;">
        <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Customers Directory</a>
        <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-edit"></i> Edit Account</a>
    </div>
</div>

<!-- Alert messages -->
<?php display_flash_alerts('cust_msg'); ?>
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>

<!-- Tabs navigation -->
<div style="display:flex; border-bottom:2px solid var(--color-border); margin-bottom:var(--space-5); gap:16px;" class="admin-tab-nav">
    <button class="tab-btn active" onclick="switchProfileTab(event, 'tab-overview')">Overview & Details</button>
    <button class="tab-btn" onclick="switchProfileTab(event, 'tab-orders')">Orders (<?= count($orders) ?>)</button>
    <button class="tab-btn" onclick="switchProfileTab(event, 'tab-reviews')">Reviews (<?= count($reviews) ?>)</button>
    <button class="tab-btn" onclick="switchProfileTab(event, 'tab-security')">Security & Audits</button>
    <button class="tab-btn" onclick="switchProfileTab(event, 'tab-actions')">Actions Console</button>
</div>

<!-- TAB 1: OVERVIEW -->
<div id="tab-overview" class="profile-tab-content" style="display:block;">
    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:var(--space-6);" class="admin-dashboard-layout">
        
        <!-- Left: Basic info grids -->
        <div style="display:flex; flex-direction:column; gap:var(--space-4);">
            <!-- Core Details Grid -->
            <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Customer Core Details</h3>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;" class="grid-2">
                    <div>
                        <span style="font-size:10px; color:var(--color-text-faint); text-transform:uppercase; display:block;">Full Name</span>
                        <strong><?= e($u['full_name']) ?></strong>
                    </div>
                    <div>
                        <span style="font-size:10px; color:var(--color-text-faint); text-transform:uppercase; display:block;">Email Address</span>
                        <strong><?= e($u['email']) ?></strong>
                        <?php if ($u['email_verified'] ?? false): ?>
                            <span class="status-pill pill-completed" style="font-size:8px; padding:2px 4px; display:inline-block; margin-left:4px;">VERIFIED</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span style="font-size:10px; color:var(--color-text-faint); text-transform:uppercase; display:block;">Phone Number</span>
                        <strong><?= e($u['phone'] ?: 'N/A') ?></strong>
                        <?php if ($u['phone_verified'] ?? false): ?>
                            <span class="status-pill pill-completed" style="font-size:8px; padding:2px 4px; display:inline-block; margin-left:4px;">VERIFIED</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span style="font-size:10px; color:var(--color-text-faint); text-transform:uppercase; display:block;">Gender / Date of Birth</span>
                        <strong><?= e($u['gender'] ?: 'N/A') ?> / <?= $u['dob'] ? date('M d, Y', strtotime($u['dob'])) : 'N/A' ?></strong>
                    </div>
                </div>
            </div>

            <!-- Address Book -->
            <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Addresses Registry (<?= count($addressBook) ?>)</h3>
                <?php if (!empty($addressBook)): ?>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;" class="grid-2">
                        <?php foreach ($addressBook as $addr): ?>
                            <div style="border:1px solid var(--color-border); padding:12px; border-radius:var(--radius-sm); font-size:12px; background:var(--color-bg);">
                                <span class="status-pill pill-completed" style="font-size:8px; margin-bottom:6px; display:inline-block;"><?= strtoupper($addr['address_type'] ?? 'Address') ?></span>
                                <?php if ($addr['is_default']): ?>
                                    <span class="status-pill pill-pending" style="font-size:8px; margin-bottom:6px; display:inline-block;">DEFAULT</span>
                                <?php endif; ?>
                                <p style="margin:0 0 4px 0;"><strong>City:</strong> <?= e($addr['city']) ?> | <strong>Postal Code:</strong> <?= e($addr['postal_code'] ?? 'N/A') ?></p>
                                <p style="margin:0 0 8px 0; color:var(--color-text-muted);"><?= e($addr['address_line1']) . (!empty($addr['address_line2']) ? ', ' . e($addr['address_line2']) : '') ?></p>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($addr['address_line1'] . ', ' . $addr['city']) ?>" target="_blank" style="font-size:11px; color:var(--color-primary); font-weight:700;"><i class="fas fa-map-location-dot"></i> Google Map Link</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="font-size:12px; color:var(--color-text-faint); margin:0;">No shipping or billing addresses recorded for this user.</p>
                <?php endif; ?>
            </div>

            <!-- Current Cart Details -->
            <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Shopping Cart Details</h3>
                <?php if (!empty($cart)): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:var(--color-bg); padding:12px; border-radius:var(--radius-sm); margin-bottom:12px; border:1px solid var(--color-border);">
                        <span>Total Items: <strong><?= count($cart) ?></strong></span>
                        <strong style="color:var(--color-primary); font-size:16px;">Value: ৳<?= number_format($cartTotal, 2) ?></strong>
                    </div>
                    <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px; max-height: 200px; overflow-y: auto;">
                        <?php foreach ($cart as $item): ?>
                            <li style="display:flex; justify-content:space-between; align-items:center; font-size:12px; padding-bottom:6px; border-bottom:1px solid var(--color-border);">
                                <span><?= e($item['name']) ?> <strong style="color:var(--color-text-muted);">x<?= $item['quantity'] ?></strong></span>
                                <strong>৳<?= number_format((float)$item['price'] * $item['quantity'], 2) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="font-size:12px; color:var(--color-text-faint); margin:0;">Shopping cart is currently empty (or abandoned).</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Aggregates, charts & timelines -->
        <div style="display:flex; flex-direction:column; gap:var(--space-4);">
            <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Customer Analytics</h3>
                
                <div style="display:flex; flex-direction:column; gap:12px; font-size:13px;">
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:var(--color-text-muted);">Lifetime Spending:</span>
                        <strong>৳<?= number_format((float)$analytics['total_spent'], 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:var(--color-text-muted);">Average Order Value:</span>
                        <strong>৳<?= number_format((float)$analytics['aov'], 2) ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:var(--color-text-muted);">Completed Purchases:</span>
                        <strong><?= (int)$analytics['total_orders'] ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:var(--color-text-muted);">Product Reviews:</span>
                        <strong><?= (int)$analytics['reviews_count'] ?></strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:var(--color-text-muted);">Wishlist items:</span>
                        <strong><?= (int)$analytics['wishlist_count'] ?></strong>
                    </div>
                </div>
            </div>

            <!-- Activity Logs short view -->
            <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 12px 0;">Recent Activities</h3>
                <?php if (!empty($activities)): ?>
                    <ul style="list-style:none; padding:0; margin:0; font-size:11px; display:flex; flex-direction:column; gap:8px;">
                        <?php foreach ($activities as $log): ?>
                            <li>
                                <div style="display:flex; justify-content:space-between; font-weight:600; color:var(--color-text);">
                                    <span><?= e($log['activity_type']) ?></span>
                                    <span style="color:var(--color-text-faint); font-weight:400;"><?= date('M d, H:i', strtotime($log['created_at'])) ?></span>
                                </div>
                                <span style="color:var(--color-text-muted);"><?= e($log['description']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="font-size:11px; color:var(--color-text-faint); margin:0;">No actions logged recently.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- TAB 2: ORDERS & WISHLIST -->
<div id="tab-orders" class="profile-tab-content" style="display:none;">
    <!-- Order history -->
    <div class="dashboard-card" style="padding:0; overflow:hidden; margin-bottom:var(--space-4);">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0;">Orders History</h3>
        </div>
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:16px 20px;">Order ID</th>
                        <th style="padding:16px 20px;">Total Price</th>
                        <th style="padding:16px 20px;">Payment Method</th>
                        <th style="padding:16px 20px;">Delivery Status</th>
                        <th style="padding:16px 20px;">Date Created</th>
                        <th style="padding:16px 20px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $o): ?>
                            <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                                <td style="padding:12px 20px;"><strong>#<?= $o['id'] ?></strong></td>
                                <td style="padding:12px 20px; font-weight:700;">৳<?= number_format((float)$o['total_amount'], 2) ?></td>
                                <td style="padding:12px 20px; text-transform:uppercase;"><?= e($o['payment_method']) ?></td>
                                <td style="padding:12px 20px;">
                                    <span class="status-pill pill-<?= $o['status'] === 'delivered' ? 'completed' : ($o['status'] === 'cancelled' ? 'cancelled' : 'pending') ?>" style="font-size:8px;">
                                        <?= strtoupper($o['status']) ?>
                                    </span>
                                </td>
                                <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('M d, Y H:i', strtotime($o['created_at'])) ?></td>
                                <td style="padding:12px 20px; text-align:right;">
                                    <a href="../orders/view.php?id=<?= $o['id'] ?>" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-eye"></i> Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding:24px; text-align:center; color:var(--color-text-faint);">No order history transactions recorded.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Wishlist items -->
    <div class="dashboard-card" style="padding:0; overflow:hidden;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0;">Active Wishlist Items (<?= count($wishlistItems) ?>)</h3>
        </div>
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:16px 20px; width:60px;">Image</th>
                        <th style="padding:16px 20px;">Product Name</th>
                        <th style="padding:16px 20px; width:120px; text-align:right;">Unit Price</th>
                        <th style="padding:16px 20px; width:150px;">Date Added</th>
                        <th style="padding:16px 20px; width:100px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($wishlistItems)): ?>
                        <?php foreach ($wishlistItems as $wi): 
                            $wiImg = image_url($wi['image'], 'products');
                        ?>
                            <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                                <td style="padding:12px 20px;">
                                    <div style="width:40px; height:40px; border-radius:var(--radius-sm); overflow:hidden; border:1px solid var(--color-border); background:var(--color-bg);">
                                        <img src="<?= e($wiImg) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                    </div>
                                </td>
                                <td style="padding:12px 20px;"><strong><?= e($wi['name']) ?></strong></td>
                                <td style="padding:12px 20px; text-align:right; font-weight:700; color:var(--color-primary);">৳<?= number_format((float)$wi['price'], 2) ?></td>
                                <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('M d, Y H:i', strtotime($wi['created_at'])) ?></td>
                                <td style="padding:12px 20px; text-align:right;">
                                    <form method="post" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="admin_action" value="remove_wishlist">
                                        <input type="hidden" name="product_id" value="<?= $wi['product_id'] ?>">
                                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Remove this item from wishlist?');" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; border:none; cursor:pointer;"><i class="fas fa-trash"></i> Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding:24px; text-align:center; color:var(--color-text-faint);">Wishlist is empty.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB 3: REVIEWS -->
<div id="tab-reviews" class="profile-tab-content" style="display:none;">
    <div class="dashboard-card" style="padding:0; overflow:hidden;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0;">Submitted Customer Reviews</h3>
        </div>
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:16px 20px;">Product</th>
                        <th style="padding:16px 20px; width:100px; text-align:center;">Rating</th>
                        <th style="padding:16px 20px;">Comment</th>
                        <th style="padding:16px 20px; width:150px;">Review Date</th>
                        <th style="padding:16px 20px; width:100px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reviews)): ?>
                        <?php foreach ($reviews as $rev): ?>
                            <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                                <td style="padding:12px 20px;"><strong><?= e($rev['product_name']) ?></strong></td>
                                <td style="padding:12px 20px; text-align:center; color:#f08c00; font-weight:700;">
                                    <?= str_repeat('★', (int)$rev['rating']) ?><?= str_repeat('☆', 5 - (int)$rev['rating']) ?>
                                </td>
                                <td style="padding:12px 20px; color:var(--color-text-muted); max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="<?= e($rev['comment']) ?>"><?= e($rev['comment']) ?></td>
                                <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('M d, Y H:i', strtotime($rev['created_at'])) ?></td>
                                <td style="padding:12px 20px; text-align:right;">
                                    <form method="post" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="admin_action" value="delete_review">
                                        <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                        <button type="submit" class="btn btn-secondary" onclick="return confirm('Permanently delete this customer review?');" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; border:none; cursor:pointer;"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding:24px; text-align:center; color:var(--color-text-faint);">No product reviews submitted by this customer.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB 4: SECURITY & LOGS -->
<div id="tab-security" class="profile-tab-content" style="display:none;">
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:var(--space-6);" class="admin-dashboard-layout">
        
        <!-- Left: Security Audit Events -->
        <div class="dashboard-card" style="padding:var(--space-5); margin:0;">
            <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Security Audits Event Logs</h3>
            <?php if (!empty($securityEvents)): ?>
                <ul style="list-style:none; padding:0; margin:0; font-size:12px; display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($securityEvents as $sev): ?>
                        <li style="border-bottom:1px solid var(--color-border); padding-bottom:6px;">
                            <div style="display:flex; justify-content:space-between; font-weight:700;">
                                <span style="color:#e03131; text-transform:uppercase; font-size:10px;"><i class="fas fa-shield-halved"></i> <?= e($sev['event_type']) ?></span>
                                <span style="color:var(--color-text-faint); font-weight:400;"><?= date('M d, H:i', strtotime($sev['created_at'])) ?></span>
                            </div>
                            <p style="margin:2px 0 0 0; color:var(--color-text);"><?= e($sev['description']) ?></p>
                            <span style="font-size:9px; color:var(--color-text-faint);">IP: <?= e($sev['ip_address']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p style="font-size:12px; color:var(--color-text-faint); margin:0;">No security events or resets logged.</p>
            <?php endif; ?>
        </div>

        <!-- Right: Basic trusted devices & metadata history -->
        <div class="dashboard-card" style="padding:var(--space-5); margin:0;">
            <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Trusted Login Devices & Metadata</h3>
            
            <div style="font-size:12px; color:var(--color-text-muted); line-height:1.6;">
                <p><strong>Device Operating System:</strong> <?= e($u['os'] ?? 'Unknown') ?></p>
                <p><strong>Preferred Browser:</strong> <?= e($u['browser'] ?? 'Unknown') ?></p>
                <p><strong>Last Logged IP Address:</strong> <?= e($u['last_ip_address'] ?? 'N/A') ?></p>
                <p><strong>Account Lockout failed attempts count:</strong> <?= (int) ($u['failed_logins'] ?? 0) ?></p>
                <p><strong>Password Last Modified:</strong> <?= $u['last_password_change'] ? date('M d, Y H:i', strtotime($u['last_password_change'])) : 'Never updated' ?></p>
            </div>
        </div>

    </div>
</div>

<!-- TAB 5: ACTIONS CONSOLE -->
<div id="tab-actions" class="profile-tab-content" style="display:none;">
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:var(--space-6);" class="admin-dashboard-layout">
        
        <!-- Left: Password resets, Lockouts & Session controllers -->
        <div style="display:flex; flex-direction:column; gap:var(--space-4);">
            
            <!-- Ban and suspension card -->
            <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Account Locking (Ban / Unban)</h3>
                
                <form method="post" class="auth-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="admin_action" value="toggle_ban">
                    
                    <?php if ($u['is_banned']): ?>
                        <input type="hidden" name="ban_val" value="0">
                        <p style="font-size:12px; color:#e03131; margin-bottom:12px;"><strong>BANNED Account. Reason:</strong> <?= e($u['ban_reason'] ?: 'None provided') ?></p>
                        <button type="submit" class="btn btn-primary" style="background:#0ca678; border:none; width:100%; border-radius:var(--radius-pill); font-weight:700; padding:8px;"><i class="fas fa-unlock"></i> Lift Ban & Activate</button>
                    <?php else: ?>
                        <input type="hidden" name="ban_val" value="1">
                        <div class="form-field-group">
                            <label style="font-weight:700;">Ban Statement Reason *</label>
                            <input type="text" name="ban_reason" placeholder="Type ban statement..." required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                        </div>
                        <button type="submit" class="btn btn-primary" style="background:#f03e3e; border:none; width:100%; border-radius:var(--radius-pill); font-weight:700; padding:8px;"><i class="fas fa-ban"></i> Apply Permanent Account Ban</button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Password Reset card -->
            <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Administrative Password Force</h3>
                <form method="post" class="auth-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="admin_action" value="reset_password">
                    
                    <div class="form-field-group">
                        <label style="font-weight:700;">New Security Password *</label>
                        <input type="password" name="new_password" required placeholder="Type new user password..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:8px;"><i class="fas fa-key"></i> Force Password Reset</button>
                </form>
            </div>

            <!-- Sessions Terminate card -->
            <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 12px 0;">Active Session Expiration</h3>
                <form method="post" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="admin_action" value="force_logout">
                    <button type="submit" class="btn btn-secondary" onclick="return confirm('Force log out this user from all devices?');" style="width:100%; border-radius:var(--radius-pill); font-weight:700; padding:8px;"><i class="fas fa-right-from-bracket"></i> Force Terminate Active Logins</button>
                </form>
            </div>

        </div>

        <!-- Right: Dispatcher Notification & simulated email forms -->
        <div style="display:flex; flex-direction:column; gap:var(--space-4);">
            
            <!-- Send Notification card -->
            <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Send In-App Notification</h3>
                <form method="post" class="auth-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="admin_action" value="send_notification">

                    <div class="form-field-group">
                        <label style="font-weight:700;">Notification Title *</label>
                        <input type="text" name="notif_title" required placeholder="E.g. Discount offer just for you!" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                    </div>

                    <div class="form-field-group">
                        <label style="font-weight:700;">Message *</label>
                        <textarea name="notif_msg" rows="3" required placeholder="Type the alert details..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; resize:vertical;"></textarea>
                    </div>

                    <div class="form-field-group">
                        <label style="font-weight:700;">Notification Type</label>
                        <select name="notif_type" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                            <option value="account">Account Alert</option>
                            <option value="order">Order Update</option>
                            <option value="coupon">Coupon Discount Notice</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:8px;"><i class="fas fa-paper-plane"></i> Dispatch Notification</button>
                </form>
            </div>

            <!-- Send Simulated Email card -->
            <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
                <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Send Email Communication</h3>
                <form method="post" class="auth-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="admin_action" value="send_email">

                    <div class="form-field-group">
                        <label style="font-weight:700;">Select Template Type</label>
                        <select name="email_type" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                            <option value="welcome">Welcome Onboarding Email</option>
                            <option value="password_reset">Password Recovery URL</option>
                            <option value="promotion">Promo Event Notification</option>
                            <option value="ban_notice">Account Lockout Suspension Warning</option>
                        </select>
                    </div>

                    <div class="form-field-group">
                        <label style="font-weight:700;">Email Subject *</label>
                        <input type="text" name="email_subject" required placeholder="Subject line..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                    </div>

                    <div class="form-field-group">
                        <label style="font-weight:700;">Email HTML Body Content *</label>
                        <textarea name="email_body" rows="4" required placeholder="HTML or Plain Text content..." style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; resize:vertical;"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:8px;"><i class="fas fa-envelope"></i> Send Email Outbox</button>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
function switchProfileTab(evt, tabId) {
    const contents = document.querySelectorAll('.profile-tab-content');
    contents.forEach(el => {
        el.style.display = 'none';
    });

    const buttons = document.querySelectorAll('.tab-btn');
    buttons.forEach(el => {
        el.classList.remove('active');
    });

    document.getElementById(tabId).style.display = 'block';
    evt.currentTarget.classList.add('active');
}
</script>

<style>
.tab-btn {
    background: none;
    border: none;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text-muted);
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
}
.tab-btn:hover {
    color: var(--color-primary);
}
.tab-btn.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
}
</style>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
