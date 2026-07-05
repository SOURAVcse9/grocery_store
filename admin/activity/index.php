<?php
/**
 * ==========================================================================
 * admin/activity/index.php — Backoffice Activity Log Viewer
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Activity Logs — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('activity.view');

$pdo = db();

// Handle Purging of Logs
if (method_is('post') && input('action', '') === 'purge_logs') {
    verify_csrf_or_fail();
    // Only Super Admins (role_id = 1) can clear activity logs
    if ((int)current_admin_role_id() === 1) {
        try {
            $pdo->exec("TRUNCATE TABLE admin_activity_logs");
            log_admin_activity('activity.purge', 'Purged all backoffice activity logs.');
            flash('act_msg', 'Activity logs database table successfully truncated!', 'success');
        } catch (PDOException $e) {
            error_log('[admin/activity] Purge failed: ' . $e->getMessage());
            flash('act_msg', 'Failed to truncate logs table.', 'error');
        }
    } else {
        flash('act_msg', 'Access denied. Only Super Administrators can clear activity logs.', 'error');
    }
    redirect(current_url());
}

// Filters & Query Parameters
$search = trim(input('search', '', 'get'));
$actionFilter = trim(input('action_type', '', 'get'));

$page = (int) input('page', '1', 'get');
if ($page < 1) $page = 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Construct SQL query conditions
$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = '(a.username LIKE :search OR l.description LIKE :search OR l.activity_type LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if (!empty($actionFilter)) {
    $where[] = 'l.activity_type = :action_type';
    $params['action_type'] = $actionFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

try {
    $countSql = "
        SELECT COUNT(*) 
        FROM admin_activity_logs l
        LEFT JOIN admins a ON a.id = l.admin_id
        {$whereClause}
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalCount / $limit);

    // Fetch listings
    $selectSql = "
        SELECT l.*, a.username 
        FROM admin_activity_logs l
        LEFT JOIN admins a ON a.id = l.admin_id
        {$whereClause}
        ORDER BY l.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($selectSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();

    // Get unique activity types for filter dropdown
    $activityTypes = $pdo->query("SELECT DISTINCT activity_type FROM admin_activity_logs ORDER BY activity_type ASC")->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log('[admin/activity] load failed: ' . $e->getMessage());
    $logs = [];
    $activityTypes = [];
    $totalCount = 0;
    $totalPages = 1;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Administrative Activity Logs</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect chronological backoffice audits, system settings updates, and operator modifications.</p>
    </div>
    
    <div style="display:flex; gap:8px;">
        <!-- Purge action -->
        <?php if ((int)current_admin_role_id() === 1): ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Trashing logs database tables is permanent. Proceed?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="purge_logs">
                <button type="submit" class="btn btn-secondary" style="background:#f03e3e; color:#fff; border:none; border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-trash-can"></i> Clear All Logs</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Alert messages -->
<?php if (has_flash('act_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('act_msg') ?>
    </div>
<?php endif; ?>

<!-- Filter bar -->
<div class="dashboard-card" style="padding:var(--space-5); margin-bottom:var(--space-4);">
    <form method="get" style="display:flex; gap:12px; align-items:end; max-width:600px;">
        <div class="form-field-group" style="margin:0; flex:1.5;">
            <input type="text" name="search" placeholder="Search logs, operators..." value="<?= e($search) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>
        <div class="form-field-group" style="margin:0; flex:1;">
            <select name="action_type" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">All Actions</option>
                <?php foreach ($activityTypes as $type): ?>
                    <option value="<?= e($type) ?>" <?= $actionFilter === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:9px 18px; border:none; border-radius:var(--radius-pill); font-weight:700;">Filter</button>
        <a href="index.php" class="btn btn-secondary" style="padding:9px 18px; border-radius:var(--radius-pill); text-decoration:none; display:inline-block; font-weight:700; text-align:center;">Clear</a>
    </form>
</div>

<!-- Logs table card -->
<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:12px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px; width:150px;">Timestamp</th>
                    <th style="padding:16px 20px; width:120px;">Operator</th>
                    <th style="padding:16px 20px; width:150px;">Action Key</th>
                    <th style="padding:16px 20px;">Detailed Description</th>
                    <th style="padding:16px 20px; width:130px;">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $row): ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('Y-m-d H:i:s', strtotime($row['created_at'])) ?></td>
                            <td style="padding:12px 20px;"><strong><?= $row['username'] ? '@' . e($row['username']) : 'System' ?></strong></td>
                            <td style="padding:12px 20px;"><span class="status-pill pill-completed" style="font-size:9px;"><?= strtoupper($row['activity_type']) ?></span></td>
                            <td style="padding:12px 20px; color:var(--color-text-muted);"><?= e($row['description']) ?></td>
                            <td style="padding:12px 20px; font-family:monospace;"><?= e($row['ip_address']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding:32px; text-align:center; color:var(--color-text-faint);">No system activity log entries found.</td>
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

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
