<?php
/**
 * ==========================================================================
 * admin/customers/activity.php — Customer Activity Logs Timeline
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Activity Timeline — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('customers.view');

$pdo = db();
$userId = (int) input('id', '0', 'get');

if ($userId <= 0) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id AND role_id != 1 LIMIT 1");
    $stmt->execute(['id' => $userId]);
    $u = $stmt->fetch();

    if (!$u) {
        flash('cust_msg', 'Customer details not found.', 'error');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('[admin/customers/activity] load failed: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

$page = (int) input('page', '1', 'get');
if ($page < 1) $page = 1;
$limit = 15;
$offset = ($page - 1) * $limit;

try {
    // Count matches
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM customer_activity_logs WHERE user_id = ?");
    $cntStmt->execute([$userId]);
    $totalCount = (int) $cntStmt->fetchColumn();
    $totalPages = (int) ceil($totalCount / $limit);

    // Fetch activities list
    $stmtLogs = $pdo->prepare("
        SELECT * FROM customer_activity_logs 
        WHERE user_id = :uid 
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmtLogs->bindValue('uid', $userId, PDO::PARAM_INT);
    $stmtLogs->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmtLogs->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmtLogs->execute();
    $logs = $stmtLogs->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/customers/activity] failed: ' . $e->getMessage());
    $logs = [];
    $totalCount = 0;
    $totalPages = 1;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Activity History Timeline</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Customer: <strong><?= e($u['full_name']) ?></strong> (Email: <?= e($u['email']) ?>)</p>
    </div>
    <a href="view.php?id=<?= $userId ?>" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> View Profile</a>
</div>

<div class="dashboard-card" style="padding:var(--space-5);">
    <?php if (!empty($logs)): ?>
        <!-- Timeline display layout -->
        <div style="display:flex; flex-direction:column; gap:16px; position:relative; padding-left:20px; border-left:2px solid var(--color-border);">
            <?php foreach ($logs as $log): ?>
                <div style="position:relative; margin-bottom:4px;">
                    <!-- Dot marker -->
                    <div style="position:absolute; left:-27px; top:4px; width:12px; height:12px; border-radius:50%; background:var(--color-primary); border:2px solid #fff;"></div>
                    
                    <span style="font-size:10px; color:var(--color-text-faint); font-weight:700; text-transform:uppercase; display:block;"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></span>
                    <strong style="color:var(--color-text); font-size:13px;"><?= e($log['activity_type']) ?></strong>
                    <p style="margin:2px 0 0 0; font-size:12px; color:var(--color-text-muted);"><?= e($log['description']) ?></p>
                    <span style="font-size:9px; color:var(--color-text-faint); display:block; margin-top:2px;">IP Address: <?= e($log['ip_address'] ?? 'Unknown') ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="display:flex; justify-content:center; padding:16px; margin-top:16px; gap:6px;">
                <?php for ($p = 1; $p <= $totalPages; $p++): 
                    $paramsQuery = $_GET;
                    $paramsQuery['page'] = $p;
                    $url = '?' . http_build_query($paramsQuery);
                ?>
                    <a href="<?= $url ?>" class="pagination-link <?= $page === $p ? 'active' : '' ?>" style="display:inline-flex; width:32px; height:32px; align-items:center; justify-content:center; border:1px solid var(--color-border); border-radius:50%; text-decoration:none; font-size:11px; font-weight:700; <?= $page === $p ? 'background:var(--color-primary); color:#fff; border-color:var(--color-primary);' : 'color:var(--color-text);' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <p style="text-align:center; color:var(--color-text-faint); padding:32px; margin:0;">No logged activity timelines detected for this customer.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
