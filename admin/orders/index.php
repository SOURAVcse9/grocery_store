<?php
/**
 * ==========================================================================
 * admin/orders/index.php — Order List Manager
 * ==========================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../public/dbconnect.php';
require_once __DIR__ . '/../middleware/auth_middleware.php';

require_admin_permission('orders.view');

$pdo = db();

// Handle Bulk Actions
if (method_is('post') && isset($_POST['bulk_action'])) {
    verify_csrf_or_fail();
    
    $action = input('bulk_action', '');
    $selectedIds = $_POST['selected_orders'] ?? [];
    
    if (is_array($selectedIds) && !empty($selectedIds)) {
        $ids = array_map('intval', $selectedIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        try {
            // Validate bulk action status updates
            $validStatuses = ['pending', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'];
            
            if (in_array($action, $validStatuses, true)) {
                $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$action], $ids));
                
                // Write timelines and activity logs
                $insHist = $pdo->prepare("INSERT INTO order_status_history (order_id, status, note, created_at) VALUES (?, ?, ?, NOW())");
                foreach ($ids as $id) {
                    $insHist->execute([$id, $action, "Status updated in bulk to '" . ucfirst($action) . "' by admin."]);
                }
                
                log_admin_activity('orders.bulk_status', 'Updated status to ' . $action . ' for ' . count($ids) . ' orders in bulk.');
                flash('orders_msg', 'Selected orders status updated successfully.', 'success');
            }
        } catch (PDOException $e) {
            error_log('[admin/orders] Bulk action fail: ' . $e->getMessage());
            flash('orders_msg', 'Bulk updates failed due to internal database error.', 'error');
        }
    }
    redirect(current_url());
}

// Fetch Search and Filter query parameters
$search = trim(input('search', '', 'get'));
$statusFilter = trim(input('status', '', 'get'));
$paymentFilter = trim(input('payment_status', '', 'get'));
$courierFilter = trim(input('courier', '', 'get'));
$page = (int) input('page', '1', 'get');

if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Construct SQL query
$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = '(o.order_number LIKE :search OR u.full_name LIKE :search OR u.phone LIKE :search OR u.email LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if (!empty($statusFilter)) {
    $where[] = 'o.status = :status';
    $params['status'] = $statusFilter;
}

if (!empty($paymentFilter)) {
    $where[] = 'o.payment_status = :pay';
    $params['pay'] = $paymentFilter;
}

if (!empty($courierFilter)) {
    $where[] = 'o.courier = :courier';
    $params['courier'] = $courierFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

$pageTitle = 'Orders Manager — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
try {
    // Count total matches
    $countSql = "
        SELECT COUNT(*) 
        FROM orders o
        JOIN users u ON u.id = o.user_id
        {$whereClause}
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalCount / $limit);

    // Fetch orders listing
    $selectSql = "
        SELECT o.*, u.full_name, u.phone AS customer_phone 
        FROM orders o
        JOIN users u ON u.id = o.user_id
        {$whereClause}
        ORDER BY o.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($selectSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/orders] Index fetch fail: ' . $e->getMessage());
    $orders = [];
    $totalCount = 0;
    $totalPages = 1;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Orders Management</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect customer orders, dispatch items, record courier details, and print packing slips.</p>
    </div>
</div>

<!-- Alert messages -->
<?php if (has_flash('orders_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('orders_msg') ?>
    </div>
<?php endif; ?>

<!-- Filters Form -->
<div class="dashboard-card" style="padding:var(--space-5); margin-bottom:var(--space-4);">
    <form method="get" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)) auto; gap:12px; align-items:end;" class="grid-5">
        <!-- Search -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Search Order</label>
            <input type="text" name="search" placeholder="Number, Name, Phone..." value="<?= e($search) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>

        <!-- Status -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Order Status</label>
            <select name="status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">All Statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
                <option value="packed" <?= $statusFilter === 'packed' ? 'selected' : '' ?>>Packed</option>
                <option value="shipped" <?= $statusFilter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                <option value="out_for_delivery" <?= $statusFilter === 'out_for_delivery' ? 'selected' : '' ?>>Out For Delivery</option>
                <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>

        <!-- Payment status -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Payment Status</label>
            <select name="payment_status" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">All Payments</option>
                <option value="pending" <?= $paymentFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="paid" <?= $paymentFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="failed" <?= $paymentFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
                <option value="refunded" <?= $paymentFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
            </select>
        </div>

        <!-- Courier -->
        <div class="form-field-group" style="margin:0;">
            <label style="font-size:10px; font-weight:700; color:var(--color-text-muted); text-transform:uppercase;">Courier / Shipping</label>
            <input type="text" name="courier" placeholder="Courier service" value="<?= e($courierFilter) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>

        <!-- Buttons -->
        <div style="display:flex; gap:8px;">
            <button type="submit" class="btn btn-primary" style="padding:9px 18px; border:none; border-radius:var(--radius-pill); font-weight:700;">Filter</button>
            <a href="index.php" class="btn btn-secondary" style="padding:9px 18px; border-radius:var(--radius-pill); text-decoration:none; display:inline-block; font-weight:700; text-align:center;">Clear</a>
        </div>
    </form>
</div>

<!-- Bulk Actions Selection Form -->
<form method="post" id="ordersTableForm">
    <?= csrf_field() ?>

    <div class="dashboard-card" style="padding:0; overflow:hidden;">
        <!-- Table Action Header -->
        <div style="padding:var(--space-3) var(--space-5); border-bottom:1px solid var(--color-border); background:var(--color-bg); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <select name="bulk_action" id="bulkActionSelect" style="padding:6px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:12px; background:#fff; outline:none; font-weight:600;">
                    <option value="">Bulk Status Update</option>
                    <option value="confirmed">Set Confirmed</option>
                    <option value="processing">Set Processing</option>
                    <option value="packed">Set Packed</option>
                    <option value="shipped">Set Shipped</option>
                    <option value="delivered">Set Delivered</option>
                    <option value="cancelled">Set Cancelled</option>
                </select>
                <button type="submit" class="btn btn-primary" onclick="return confirmBulkAction();" style="padding:6px 12px; font-size:12px; border:none; border-radius:var(--radius-sm); font-weight:700;">Apply</button>
            </div>
            <span style="font-size:12px; color:var(--color-text-muted); font-weight:600;"><?= $totalCount ?> orders found</span>
        </div>

        <!-- Orders Table -->
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:16px 20px; width:40px; text-align:center;">
                            <input type="checkbox" id="selectAllCheckbox" style="cursor:pointer; width:14px; height:14px;">
                        </th>
                        <th style="padding:16px 20px; width:110px;">Order Number</th>
                        <th style="padding:16px 20px;">Customer Profile</th>
                        <th style="padding:16px 20px; width:120px;">Date & Time</th>
                        <th style="padding:16px 20px; width:120px;">Grand Total</th>
                        <th style="padding:16px 20px; width:110px;">Payment Status</th>
                        <th style="padding:16px 20px; width:120px;">Courier</th>
                        <th style="padding:16px 20px; width:120px;">Order Status</th>
                        <th style="padding:16px 20px; width:100px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $o): 
                            $status = $o['status'];
                            
                            $paymentStatus = $o['payment_status'] ?? 'pending';
                            $payBadge = 'pill-pending';
                            if ($paymentStatus === 'paid') $payBadge = 'pill-delivered';
                            elseif ($paymentStatus === 'failed' || $paymentStatus === 'refunded') $payBadge = 'pill-cancelled';
                            
                            $orderBadge = 'pill-pending';
                            if ($status === 'delivered') $orderBadge = 'pill-delivered';
                            elseif ($status === 'cancelled') $orderBadge = 'pill-cancelled';
                            elseif ($status === 'processing' || $status === 'shipped') $orderBadge = 'pill-processing';
                        ?>
                            <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                                <td style="padding:12px 20px; text-align:center;">
                                    <input type="checkbox" name="selected_orders[]" value="<?= $o['id'] ?>" class="order-item-checkbox" style="cursor:pointer; width:14px; height:14px;">
                                </td>
                                <td style="padding:12px 20px;">
                                    <strong style="color:var(--color-primary); font-size:13px;">#<?= e($o['order_number']) ?></strong>
                                </td>
                                <td style="padding:12px 20px;">
                                    <strong style="color:var(--color-text); display:block;"><?= e($o['full_name']) ?></strong>
                                    <span style="font-size:10px; color:var(--color-text-faint);"><?= e($o['customer_phone']) ?></span>
                                </td>
                                <td style="padding:12px 20px; color:var(--color-text-muted);"><?= date('M d, Y H:i', strtotime($o['created_at'])) ?></td>
                                <td style="padding:12px 20px; font-weight:800; color:var(--color-text);">৳<?= number_format((float)$o['total_amount'], 2) ?></td>
                                <td style="padding:12px 20px;">
                                    <span class="status-pill <?= $payBadge ?>" style="font-size:9px;">
                                        <?= strtoupper($paymentStatus) ?>
                                    </span>
                                </td>
                                <td style="padding:12px 20px; color:var(--color-text-muted); font-weight:600;"><?= e($o['courier'] ?? 'Pending Assign') ?></td>
                                <td style="padding:12px 20px;">
                                    <span class="status-pill <?= $orderBadge ?>" style="font-size:9px;">
                                        <?= strtoupper($status) ?>
                                    </span>
                                </td>
                                <td style="padding:12px 20px; text-align:right;">
                                    <div style="display:inline-flex; gap:6px;">
                                        <a href="view.php?id=<?= $o['id'] ?>" class="btn btn-primary" style="padding:4px 10px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-eye"></i> View</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="padding:32px; text-align:center; color:var(--color-text-faint);">No customer orders found matching filters.</td>
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
    const items = document.querySelectorAll('.order-item-checkbox');
    
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
        alert('Please select a bulk status action first.');
        return false;
    }
    
    const checked = document.querySelectorAll('.order-item-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one order.');
        return false;
    }
    
    return confirm(`Change order status to "${action.toUpperCase()}" for ${checked.length} selected orders?`);
}
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
