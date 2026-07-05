<?php
/**
 * ==========================================================================
 * admin/newsletter/index.php — Newsletter Subscribers Directory List
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Subscribers List — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('newsletter.manage');

$pdo = db();

// Handle deletes and bulk deletes
if (method_is('post') && isset($_POST['action'])) {
    verify_csrf_or_fail();
    $action = input('action', '');
    $selectedIds = $_POST['selected_subscribers'] ?? [];
    
    if ($action === 'delete_single') {
        $subId = (int) input('id', '0');
        if ($subId > 0) {
            try {
                $pdo->prepare("DELETE FROM contact_messages WHERE id = :id AND subject = 'Newsletter Opt-in'")->execute(['id' => $subId]);
                flash('news_msg', 'Subscriber deleted successfully.', 'success');
            } catch (PDOException $e) {
                error_log('[admin/newsletter] Delete single failed: ' . $e->getMessage());
                flash('news_msg', 'Failed to delete subscriber.', 'error');
            }
        }
    } elseif ($action === 'bulk_delete' && is_array($selectedIds) && !empty($selectedIds)) {
        $ids = array_map('intval', $selectedIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        try {
            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id IN ($placeholders) AND subject = 'Newsletter Opt-in'");
            $stmt->execute($ids);
            
            log_admin_activity('newsletter.bulk_delete', 'Deleted ' . count($ids) . ' newsletter subscribers in bulk.');
            flash('news_msg', 'Selected subscribers deleted successfully.', 'success');
        } catch (PDOException $e) {
            error_log('[admin/newsletter] Bulk delete failed: ' . $e->getMessage());
            flash('news_msg', 'Bulk delete failed due to database transaction error.', 'error');
        }
    }
    redirect(current_url());
}

$search = trim(input('search', '', 'get'));
$page = (int) input('page', '1', 'get');

if ($page < 1) $page = 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Construct SQL query
$where = ["subject = 'Newsletter Opt-in'"];
$params = [];

if (!empty($search)) {
    $where[] = 'email LIKE :search';
    $params['search'] = '%' . $search . '%';
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

try {
    // Count matches
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages {$whereClause}");
    $countStmt->execute($params);
    $totalCount = (int) $countStmt->fetchColumn();
    $totalPages = (int) ceil($totalCount / $limit);

    // Fetch listing details
    $selectSql = "
        SELECT * FROM contact_messages 
        {$whereClause} 
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($selectSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $subscribers = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/newsletter] index fetch fail: ' . $e->getMessage());
    $subscribers = [];
    $totalCount = 0;
    $totalPages = 1;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Newsletter Subscribers</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect subscribed emails, delete opt-out requests, and export raw contact details to CSV sheets.</p>
    </div>
    <a href="export.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-file-csv"></i> Export CSV</a>
</div>

<!-- Alert messages -->
<?php if (has_flash('news_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('news_msg') ?>
    </div>
<?php endif; ?>

<!-- Filter & Search Form -->
<div class="dashboard-card" style="padding:var(--space-5); margin-bottom:var(--space-4);">
    <form method="get" style="display:flex; gap:12px; align-items:end; max-width:500px;">
        <div class="form-field-group" style="margin:0; flex:1;">
            <input type="text" name="search" placeholder="Search by email..." value="<?= e($search) ?>" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>
        <button type="submit" class="btn btn-primary" style="padding:9px 18px; border:none; border-radius:var(--radius-pill); font-weight:700;">Filter</button>
        <a href="index.php" class="btn btn-secondary" style="padding:9px 18px; border-radius:var(--radius-pill); text-decoration:none; display:inline-block; font-weight:700; text-align:center;">Clear</a>
    </form>
</div>

<!-- Subscribers table card -->
<form method="post" id="subscribersTableForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="bulk_delete">

    <div class="dashboard-card" style="padding:0; overflow:hidden;">
        <!-- Bulk deletion action header -->
        <div style="padding:var(--space-3) var(--space-5); border-bottom:1px solid var(--color-border); background:var(--color-bg); display:flex; justify-content:space-between; align-items:center;">
            <button type="submit" class="btn btn-secondary" onclick="return confirm('Bulk delete selected subscribers?');" style="padding:6px 12px; font-size:12px; background:#f03e3e; color:#fff; border:none; border-radius:var(--radius-sm); font-weight:700;"><i class="fas fa-trash"></i> Bulk Delete</button>
            <span style="font-size:12px; color:var(--color-text-muted); font-weight:600;"><?= $totalCount ?> subscribers found</span>
        </div>

        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:13px;">
                <thead>
                    <tr>
                        <th style="padding:16px 20px; width:40px; text-align:center;">
                            <input type="checkbox" id="selectAllCheckbox" style="cursor:pointer; width:14px; height:14px;">
                        </th>
                        <th style="padding:16px 20px;">Subscriber Email</th>
                        <th style="padding:16px 20px; width:150px;">Subscription Status</th>
                        <th style="padding:16px 20px; width:200px;">Subscription Date</th>
                        <th style="padding:16px 20px; width:100px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($subscribers)): ?>
                        <?php foreach ($subscribers as $sub): ?>
                            <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                                <td style="padding:12px 20px; text-align:center;">
                                    <input type="checkbox" name="selected_subscribers[]" value="<?= $sub['id'] ?>" class="sub-item-checkbox" style="cursor:pointer; width:14px; height:14px;">
                                </td>
                                <td style="padding:12px 20px;">
                                    <strong style="color:var(--color-text); font-size:14px;"><?= e($sub['email']) ?></strong>
                                </td>
                                <td style="padding:12px 20px;">
                                    <span class="status-pill pill-completed" style="font-size:9px;">SUBSCRIBED</span>
                                </td>
                                <td style="padding:12px 20px; color:var(--color-text-muted);"><?= date('M d, Y H:i', strtotime($sub['created_at'])) ?></td>
                                <td style="padding:12px 20px; text-align:right;">
                                    <button type="button" onclick="deleteSingleSubscriber(<?= $sub['id'] ?>);" class="btn btn-secondary" style="padding:4px 10px; font-size:11px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; border:none; cursor:pointer;"><i class="fas fa-trash"></i> Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding:32px; text-align:center; color:var(--color-text-faint);">No subscribers registered.</td>
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

<!-- Single deletion utility form -->
<form method="post" id="deleteSingleForm" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_single">
    <input type="hidden" name="id" id="deleteSingleId">
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const selectAll = document.getElementById('selectAllCheckbox');
    const items = document.querySelectorAll('.sub-item-checkbox');
    
    if (selectAll) {
        selectAll.addEventListener('change', () => {
            items.forEach(box => {
                box.checked = selectAll.checked;
            });
        });
    }
});

function deleteSingleSubscriber(id) {
    if (confirm('Permanently delete this subscriber?')) {
        document.getElementById('deleteSingleId').value = id;
        document.getElementById('deleteSingleForm').submit();
    }
}
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
