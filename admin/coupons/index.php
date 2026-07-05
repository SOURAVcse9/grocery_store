<?php
/**
 * ==========================================================================
 * admin/coupons/index.php — Coupon Code Manager
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Coupons Manager — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('coupons.manage');

$pdo = db();

// Process Bulk Actions
if (method_is('post') && isset($_POST['bulk_action'])) {
    verify_csrf_or_fail();
    $action = input('bulk_action', '');
    $selectedIds = $_POST['selected_coupons'] ?? [];

    if (is_array($selectedIds) && !empty($selectedIds)) {
        $ids = array_map('intval', $selectedIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            if ($action === 'enable') {
                $stmt = $pdo->prepare("UPDATE coupons SET is_active = 1 WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                flash('coupon_msg', 'Selected coupons enabled.', 'success');
            } elseif ($action === 'disable') {
                $stmt = $pdo->prepare("UPDATE coupons SET is_active = 0 WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                flash('coupon_msg', 'Selected coupons disabled.', 'success');
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM coupons WHERE id IN ($placeholders)");
                $stmt->execute($ids);
                flash('coupon_msg', 'Selected coupons deleted permanently.', 'success');
            }
        } catch (PDOException $e) {
            error_log('[admin/coupons] Bulk action failed: ' . $e->getMessage());
            flash('coupon_msg', 'Failed to execute bulk updates.', 'error');
        }
    }
    redirect(current_url());
}

$search = trim(input('search', '', 'get'));
$page = (int) input('page', '1', 'get');

if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Construct SQL query
$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = 'code LIKE :search';
    $params['search'] = '%' . $search . '%';
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM coupons {$whereClause}");
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalCount / $limit);

    $stmt = $pdo->prepare("
        SELECT * FROM coupons 
        {$whereClause} 
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $coupons = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/coupons] load failed: ' . $e->getMessage());
    $coupons = [];
    $totalCount = 0;
    $totalPages = 1;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Coupons Directory</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Create and configure percentage, fixed value, or free shipping discount coupons and cart requirements.</p>
    </div>
    <a href="create.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-plus"></i> Add Coupon</a>
</div>

<!-- Alert messages -->
<?php if (has_flash('coupon_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('coupon_msg') ?>
    </div>
<?php endif; ?>

<!-- Search Form -->
<div class="dashboard-card" style="padding:var(--space-5); margin-bottom:var(--space-4);">
    <form method="get" style="display:flex; gap:12px; align-items:end; max-width:500px;">
        <div class="form-field-group" style="margin:0; flex:1;">
            <input type="text" name="search" placeholder="Search by coupon code..." value="<?= e($search) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>
        <button type="submit" class="btn btn-primary" style="padding:9px 18px; border:none; border-radius:var(--radius-pill); font-weight:700;">Filter</button>
        <a href="index.php" class="btn btn-secondary" style="padding:9px 18px; border-radius:var(--radius-pill); text-decoration:none; display:inline-block; font-weight:700; text-align:center;">Clear</a>
    </form>
</div>

<!-- Grid / Table -->
<form method="post" id="couponsTableForm">
    <?= csrf_field() ?>

    <div class="dashboard-card" style="padding:0; overflow:hidden;">
        <!-- Action Header -->
        <div style="padding:var(--space-3) var(--space-5); border-bottom:1px solid var(--color-border); background:var(--color-bg); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; align-items:center; gap:10px;">
                <select name="bulk_action" id="bulkActionSelect" style="padding:6px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:12px; background:#fff; outline:none; font-weight:600;">
                    <option value="">Bulk Coupon Actions</option>
                    <option value="enable">Enable Selected</option>
                    <option value="disable">Disable Selected</option>
                    <option value="delete">Delete Selected</option>
                </select>
                <button type="submit" class="btn btn-primary" onclick="return confirmBulkAction();" style="padding:6px 12px; font-size:12px; border:none; border-radius:var(--radius-sm); font-weight:700;">Apply</button>
            </div>
            <span style="font-size:12px; color:var(--color-text-muted); font-weight:600;"><?= $totalCount ?> coupons found</span>
        </div>

        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:13px;">
                <thead>
                    <tr>
                        <th style="padding:16px 20px; width:40px; text-align:center;">
                            <input type="checkbox" id="selectAllCheckbox" style="cursor:pointer; width:14px; height:14px;">
                        </th>
                        <th style="padding:16px 20px;">Coupon Code</th>
                        <th style="padding:16px 20px; width:120px;">Type</th>
                        <th style="padding:16px 20px; width:150px; text-align:right;">Value / Discount</th>
                        <th style="padding:16px 20px; width:150px; text-align:center;">Times Used</th>
                        <th style="padding:16px 20px; width:180px;">Validity Range</th>
                        <th style="padding:16px 20px; width:100px;">Status</th>
                        <th style="padding:16px 20px; width:180px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($coupons)): ?>
                        <?php foreach ($coupons as $c): 
                            $status = (bool) ($c['is_active'] ?? true);
                            $type = $c['type'] ?? 'percentage';
                            
                            $valStr = 'Free Shipping';
                            if ($type === 'percentage') {
                                $valStr = (int)$c['discount_percent'] . '%';
                            } elseif ($type === 'fixed') {
                                $valStr = '৳' . number_format((float)$c['discount_amount'], 2);
                            }
                        ?>
                            <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                                <td style="padding:12px 20px; text-align:center;">
                                    <input type="checkbox" name="selected_coupons[]" value="<?= $c['id'] ?>" class="coupon-item-checkbox" style="cursor:pointer; width:14px; height:14px;">
                                </td>
                                <td style="padding:12px 20px;">
                                    <strong style="color:var(--color-primary); font-size:14px;"><?= e($c['code']) ?></strong>
                                </td>
                                <td style="padding:12px 20px; text-transform:uppercase; font-size:11px; font-weight:700; color:var(--color-text-muted);"><?= str_replace('_', ' ', $type) ?></td>
                                <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-text);"><?= $valStr ?></td>
                                <td style="padding:12px 20px; text-align:center; font-weight:700; color:var(--color-text-muted);"><?= (int) $c['times_used'] ?> / <?= (int)$c['usage_limit'] ?></td>
                                <td style="padding:12px 20px; color:var(--color-text-faint); font-size:11px;">
                                    <?= date('M d, Y', strtotime($c['valid_from'])) ?> – <?= date('M d, Y', strtotime($c['valid_until'])) ?>
                                </td>
                                <td style="padding:12px 20px;">
                                    <span class="status-pill pill-<?= $status ? 'completed' : 'cancelled' ?>" style="font-size:9px;">
                                        <?= $status ? 'Active' : 'Disabled' ?>
                                    </span>
                                </td>
                                <td style="padding:12px 20px; text-align:right;">
                                    <div style="display:inline-flex; gap:6px;">
                                        <a href="edit.php?id=<?= $c['id'] ?>" class="btn btn-primary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-pen"></i> Edit</a>
                                        <a href="duplicate.php?id=<?= $c['id'] ?>" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;" title="Duplicate / Clone"><i class="far fa-copy"></i> Copy</a>
                                        <a href="delete.php?id=<?= $c['id'] ?>" onclick="return confirm('Permanently delete this coupon code?');" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; text-decoration:none;" title="Delete coupon"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="padding:32px; text-align:center; color:var(--color-text-faint);">No coupon discount codes registered. Click "Add Coupon" to create one.</td>
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
    const items = document.querySelectorAll('.coupon-item-checkbox');
    
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
    
    const checked = document.querySelectorAll('.coupon-item-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one coupon.');
        return false;
    }
    
    return confirm(`Apply bulk action "${action.toUpperCase()}" to ${checked.length} selected coupons?`);
}
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
