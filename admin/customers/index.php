<?php
/**
 * ==========================================================================
 * admin/customers/index.php — Enterprise Customers Directory
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Customers Directory — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('customers.view');

$pdo = db();

// Handle Bulk & Single Account Actions
if (method_is('post') && isset($_POST['action'])) {
    verify_csrf_or_fail();
    $action = input('action', '');
    $selectedIds = $_POST['selected_customers'] ?? [];

    if ($action === 'single_action') {
        $singleId = (int) input('id', '0');
        $selectedIds = [$singleId];
        $action = input('sub_action', '');
    }

    if (is_array($selectedIds) && !empty($selectedIds)) {
        $ids = array_map('intval', $selectedIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            if ($action === 'activate') {
                $stmt = $pdo->prepare("UPDATE users SET is_active = 1, is_banned = 0 WHERE id IN ($placeholders) AND role_id != 1");
                $stmt->execute($ids);
                foreach ($ids as $uid) {
                    $pdo->prepare("INSERT INTO customer_security_logs (user_id, event_type, description, ip_address) VALUES (?, 'activate', 'Account activated by store admin.', ?)")->execute([$uid, $_SERVER['REMOTE_ADDR']]);
                }
                flash('cust_msg', 'Selected customer accounts activated.', 'success');
            } elseif ($action === 'deactivate') {
                $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id IN ($placeholders) AND role_id != 1");
                $stmt->execute($ids);
                foreach ($ids as $uid) {
                    $pdo->prepare("INSERT INTO customer_security_logs (user_id, event_type, description, ip_address) VALUES (?, 'deactivate', 'Account deactivated by store admin.', ?)")->execute([$uid, $_SERVER['REMOTE_ADDR']]);
                }
                flash('cust_msg', 'Selected customer accounts deactivated.', 'success');
            } elseif ($action === 'ban') {
                $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, is_active = 0, ban_reason = 'Bulk ban by administrator' WHERE id IN ($placeholders) AND role_id != 1");
                $stmt->execute($ids);
                foreach ($ids as $uid) {
                    $pdo->prepare("INSERT INTO customer_security_logs (user_id, event_type, description, ip_address) VALUES (?, 'ban', 'Account banned by store admin.', ?)")->execute([$uid, $_SERVER['REMOTE_ADDR']]);
                }
                flash('cust_msg', 'Selected customer accounts banned.', 'success');
            } elseif ($action === 'unban') {
                $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, is_active = 1, ban_reason = NULL WHERE id IN ($placeholders) AND role_id != 1");
                $stmt->execute($ids);
                foreach ($ids as $uid) {
                    $pdo->prepare("INSERT INTO customer_security_logs (user_id, event_type, description, ip_address) VALUES (?, 'unban', 'Account ban lifted by store admin.', ?)")->execute([$uid, $_SERVER['REMOTE_ADDR']]);
                }
                flash('cust_msg', 'Selected customer accounts unbanned.', 'success');
            } elseif ($action === 'soft_delete') {
                $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id IN ($placeholders) AND role_id != 1");
                $stmt->execute($ids);
                foreach ($ids as $uid) {
                    $pdo->prepare("INSERT INTO customer_deleted_records (user_id, deleted_by, reason) VALUES (?, ?, 'Bulk soft deleted')")->execute([$uid, current_admin_id()]);
                }
                flash('cust_msg', 'Selected customer records moved to trash.', 'success');
            } elseif ($action === 'restore') {
                $stmt = $pdo->prepare("UPDATE users SET deleted_at = NULL WHERE id IN ($placeholders) AND role_id != 1");
                $stmt->execute($ids);
                foreach ($ids as $uid) {
                    $pdo->prepare("DELETE FROM customer_deleted_records WHERE user_id = ?")->execute([$uid]);
                }
                flash('cust_msg', 'Selected customer records restored.', 'success');
            }
        } catch (PDOException $e) {
            error_log('[admin/customers] Bulk action failed: ' . $e->getMessage());
            flash('cust_msg', 'An error occurred during status updates.', 'error');
        }
    }
    redirect(current_url());
}

// Filters & Query Parameters
$search = trim(input('search', '', 'get'));
$statusFilter = trim(input('status', '', 'get')); // active, inactive, banned, trash
$verificationFilter = trim(input('verification', '', 'get')); // verified, email_verified, phone_verified
$engagementFilter = trim(input('engagement', '', 'get')); // with_orders, without_orders, with_reviews, with_wishlist
$sortBy = trim(input('sort_by', 'newest', 'get')); // newest, oldest, highest_spending, top_buyers

$page = (int) input('page', '1', 'get');
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Construct SQL query conditions
$where = ['u.role_id != 1']; // Exclude admins
$params = [];

if (!empty($search)) {
    $where[] = '(u.full_name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search OR u.username LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($statusFilter === 'active') {
    $where[] = 'u.is_active = 1 AND u.is_banned = 0 AND u.deleted_at IS NULL';
} elseif ($statusFilter === 'inactive') {
    $where[] = 'u.is_active = 0 AND u.deleted_at IS NULL';
} elseif ($statusFilter === 'banned') {
    $where[] = 'u.is_banned = 1 AND u.deleted_at IS NULL';
} elseif ($statusFilter === 'trash') {
    $where[] = 'u.deleted_at IS NOT NULL';
} else {
    $where[] = 'u.deleted_at IS NULL';
}

if ($verificationFilter === 'verified') {
    $where[] = 'u.is_verified = 1';
} elseif ($verificationFilter === 'email_verified') {
    $where[] = 'u.email_verified = 1';
} elseif ($verificationFilter === 'phone_verified') {
    $where[] = 'u.phone_verified = 1';
}

if ($engagementFilter === 'with_orders') {
    $where[] = '(SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) > 0';
} elseif ($engagementFilter === 'without_orders') {
    $where[] = '(SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) = 0';
} elseif ($engagementFilter === 'with_reviews') {
    $where[] = '(SELECT COUNT(*) FROM product_reviews pr WHERE pr.user_id = u.id) > 0';
} elseif ($engagementFilter === 'with_wishlist') {
    $where[] = '(SELECT COUNT(*) FROM wishlists w WHERE w.user_id = u.id) > 0';
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

// Dynamic sorting
$orderClause = 'ORDER BY u.created_at DESC';
if ($sortBy === 'oldest') {
    $orderClause = 'ORDER BY u.created_at ASC';
} elseif ($sortBy === 'highest_spending') {
    $orderClause = 'ORDER BY lifetime_spending DESC';
} elseif ($sortBy === 'top_buyers') {
    $orderClause = 'ORDER BY total_orders DESC';
}

try {
    // 1. Fetch total matching records
    $countSql = "SELECT COUNT(*) FROM users u {$whereClause}";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalCount / $limit);

    // 2. Fetch Customer details with optimized subqueries
    $selectSql = "
        SELECT u.*,
               (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS total_orders,
               (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status = 'delivered') AS completed_orders,
               (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status = 'pending') AS pending_orders,
               (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status = 'cancelled') AS cancelled_orders,
               (SELECT COUNT(*) FROM product_reviews pr WHERE pr.user_id = u.id) AS total_reviews,
               (SELECT COUNT(*) FROM wishlists w WHERE w.user_id = u.id) AS wishlist_count,
               (SELECT COALESCE(SUM(ci.quantity), 0) FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = u.id) AS cart_count,
               (SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o WHERE o.user_id = u.id AND o.status = 'delivered') AS lifetime_spending,
               (SELECT COALESCE(ROUND(AVG(o.total_amount), 2), 0) FROM orders o WHERE o.user_id = u.id AND o.status = 'delivered') AS aov
        FROM users u
        {$whereClause}
        {$orderClause}
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($selectSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $customers = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/customers] fetch error: ' . $e->getMessage());
    $customers = [];
    $totalCount = 0;
    $totalPages = 1;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Customer Management</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect verified buyers, view user timeline activities, and enforce lockouts or password overrides.</p>
    </div>
    <div style="display:flex; gap:8px;">
        <a href="export.php?format=csv&<?= http_build_query($_GET) ?>" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-file-csv"></i> Export CSV</a>
        <a href="export.php?format=pdf&<?= http_build_query($_GET) ?>" target="_blank" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-file-pdf"></i> Export PDF</a>
    </div>
</div>

<!-- Notifications display -->
<?php if (has_flash('cust_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('cust_msg') ?>
    </div>
<?php endif; ?>

<!-- Advanced Filters -->
<div class="dashboard-card" style="padding:var(--space-5); margin-bottom:var(--space-4);">
    <form method="get" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)) auto; gap:12px; align-items:end;" class="grid-5">
        <!-- Search Keyword -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Keyword Search</label>
            <input type="text" name="search" placeholder="Name, Email, Username..." value="<?= e($search) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>

        <!-- Account Status Filter -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Account Status</label>
            <select name="status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">All Statuses</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                <option value="banned" <?= $statusFilter === 'banned' ? 'selected' : '' ?>>Banned Only</option>
                <option value="trash" <?= $statusFilter === 'trash' ? 'selected' : '' ?>>Soft-deleted (Trash)</option>
            </select>
        </div>

        <!-- Verification Filter -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Verification</label>
            <select name="verification" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">All Verifications</option>
                <option value="verified" <?= $verificationFilter === 'verified' ? 'selected' : '' ?>>Identity Verified</option>
                <option value="email_verified" <?= $verificationFilter === 'email_verified' ? 'selected' : '' ?>>Email Verified</option>
                <option value="phone_verified" <?= $verificationFilter === 'phone_verified' ? 'selected' : '' ?>>Phone Verified</option>
            </select>
        </div>

        <!-- User Engagement Filter -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Engagement</label>
            <select name="engagement" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">All Engagement</option>
                <option value="with_orders" <?= $engagementFilter === 'with_orders' ? 'selected' : '' ?>>Has Purchases</option>
                <option value="without_orders" <?= $engagementFilter === 'without_orders' ? 'selected' : '' ?>>No Purchases</option>
                <option value="with_reviews" <?= $engagementFilter === 'with_reviews' ? 'selected' : '' ?>>Submitted Reviews</option>
                <option value="with_wishlist" <?= $engagementFilter === 'with_wishlist' ? 'selected' : '' ?>>Has Wishlist Items</option>
            </select>
        </div>

        <!-- Sort Ordering -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Sort By</label>
            <select name="sort_by" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                <option value="highest_spending" <?= $sortBy === 'highest_spending' ? 'selected' : '' ?>>Highest Spending</option>
                <option value="top_buyers" <?= $sortBy === 'top_buyers' ? 'selected' : '' ?>>Top Buyers (Order Count)</option>
            </select>
        </div>

        <!-- Actions -->
        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary" style="padding:9px 18px; border:none; border-radius:var(--radius-pill); font-weight:700;">Filter</button>
            <a href="index.php" class="btn btn-secondary" style="padding:9px 18px; border-radius:var(--radius-pill); text-decoration:none; display:inline-block; font-weight:700; text-align:center;">Reset</a>
        </div>
    </form>
</div>

<!-- Bulk Actions Selection Form -->
<form method="post" id="customersTableForm">
    <?= csrf_field() ?>

    <div class="dashboard-card" style="padding:0; overflow:hidden;">
        <!-- Bulk Updates Actions Header -->
        <div style="padding:var(--space-3) var(--space-5); border-bottom:1px solid var(--color-border); background:var(--color-bg); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <select name="action" id="bulkActionSelect" style="padding:6px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:12px; background:#fff; outline:none; font-weight:600;">
                    <option value="">Bulk Account Actions</option>
                    <?php if ($statusFilter === 'trash'): ?>
                        <option value="restore">Restore Selected</option>
                    <?php else: ?>
                        <option value="activate">Activate Accounts</option>
                        <option value="deactivate">Deactivate Accounts</option>
                        <option value="ban">Ban Selected</option>
                        <option value="unban">Lift Ban</option>
                        <option value="soft_delete">Soft Delete (Move to Trash)</option>
                    <?php endif; ?>
                </select>
                <button type="submit" class="btn btn-primary" onclick="return confirmBulkAction();" style="padding:6px 12px; font-size:12px; border:none; border-radius:var(--radius-sm); font-weight:700;">Apply</button>
            </div>
            <span style="font-size:12px; color:var(--color-text-muted); font-weight:600;"><?= $totalCount ?> customers found</span>
        </div>

        <!-- DataTable -->
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:16px 20px; width:40px; text-align:center;">
                            <input type="checkbox" id="selectAllCheckbox" style="cursor:pointer; width:14px; height:14px;">
                        </th>
                        <th style="padding:16px 20px;">Customer Profile</th>
                        <th style="padding:16px 20px; width:110px;">Phone</th>
                        <th style="padding:16px 20px; width:100px; text-align:center;">Orders</th>
                        <th style="padding:16px 20px; width:110px; text-align:right;">Lifetime Spending</th>
                        <th style="padding:16px 20px; width:90px; text-align:center;">Wishlist / Cart</th>
                        <th style="padding:16px 20px; width:110px;">Joined Date</th>
                        <th style="padding:16px 20px; width:100px;">Status</th>
                        <th style="padding:16px 20px; width:140px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $u): 
                            $status = (bool) ($u['is_active'] ?? true);
                            $banned = (bool) ($u['is_banned'] ?? false);
                            
                            $statusBadge = 'pill-completed';
                            $statusText = 'Active';
                            if ($banned) {
                                $statusBadge = 'pill-cancelled';
                                $statusText = 'Banned';
                            } elseif (!$status) {
                                $statusBadge = 'pill-pending';
                                $statusText = 'Suspended';
                            }

                            $avatarUrl = image_url($u['avatar'], 'users');
                        ?>
                            <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                                <td style="padding:12px 20px; text-align:center;">
                                    <input type="checkbox" name="selected_customers[]" value="<?= $u['id'] ?>" class="customer-item-checkbox" style="cursor:pointer; width:14px; height:14px;">
                                </td>
                                <td style="padding:12px 20px;">
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="width:36px; height:36px; border-radius:50%; overflow:hidden; border:1px solid var(--color-border); background:var(--color-bg); flex-shrink:0;">
                                            <img src="<?= e($avatarUrl) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                        </div>
                                        <div>
                                            <strong style="color:var(--color-text); font-size:13px; display:block;"><?= e($u['full_name']) ?></strong>
                                            <span style="font-size:10px; color:var(--color-text-faint);"><?= e($u['email']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:12px 20px; color:var(--color-text-muted); font-weight:600;"><?= e($u['phone'] ?: 'N/A') ?></td>
                                <td style="padding:12px 20px; text-align:center; color:var(--color-text);">
                                    <strong style="display:block;"><?= (int)$u['total_orders'] ?></strong>
                                    <span style="font-size:9px; color:var(--color-text-faint);"><?= (int)$u['completed_orders'] ?> completed</span>
                                </td>
                                <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-primary);">৳<?= number_format((float)$u['lifetime_spending'], 2) ?></td>
                                <td style="padding:12px 20px; text-align:center; color:var(--color-text-muted);">
                                    <strong><?= (int)$u['wishlist_count'] ?></strong> / <strong><?= (int)$u['cart_count'] ?></strong>
                                </td>
                                <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                <td style="padding:12px 20px;">
                                    <span class="status-pill <?= $statusBadge ?>" style="font-size:9px;">
                                        <?= strtoupper($statusText) ?>
                                    </span>
                                </td>
                                <td style="padding:12px 20px; text-align:right;">
                                    <div style="display:inline-flex; gap:6px;">
                                        <a href="view.php?id=<?= $u['id'] ?>" class="btn btn-primary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;" title="View Profile"><i class="fas fa-eye"></i> View</a>
                                        <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;" title="Edit Details"><i class="fas fa-edit"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="padding:32px; text-align:center; color:var(--color-text-faint);">No customer accounts found matching criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="display:flex; justify-content:center; padding:16px; background:var(--color-bg); border-top:1px solid var(--color-border); gap:6px;">
                <?php for ($p = 1; $p <= $totalPages; $p++): 
                    $paramsQuery = $_GET;
                    $paramsQuery['page'] = $p;
                    $url = '?' . http_build_query($paramsQuery);
                ?>
                    <a href="<?= $url ?>" class="pagination-link <?= $page === $p ? 'active' : '' ?>" style="display:inline-flex; width:32px; height:32px; align-items:center; justify-content:center; border:1px solid var(--color-border); border-radius:50%; text-decoration:none; font-size:11px; font-weight:700; <?= $page === $p ? 'background:var(--color-primary); color:#fff; border-color:var(--color-primary);' : 'color:var(--color-text);' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectAll = document.getElementById('selectAllCheckbox');
    const items = document.querySelectorAll('.customer-item-checkbox');
    
    if (selectAll) {
        selectAll.addEventListener('change', () => {
            items.forEach(box => {
                box.checked = selectAll.checked;
            });
        });
    }
});

function confirmBulkAction() {
    const action = document.getElementById('bulkActionSelect').value;
    if (!action) {
        alert('Please select an action first.');
        return false;
    }
    
    const checked = document.querySelectorAll('.customer-item-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one customer.');
        return false;
    }
    
    return confirm(`Apply bulk action "${action.toUpperCase()}" to ${checked.length} selected customers?`);
}
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
