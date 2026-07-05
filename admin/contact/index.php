<?php
/**
 * ==========================================================================
 * admin/contact/index.php — Customer Service Inbox & Contact Messages List
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Contact Inbox — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('contacts.manage');

$pdo = db();

// Handle status updates
if (method_is('post') && isset($_POST['action'])) {
    verify_csrf_or_fail();
    $action = input('action', '');
    $msgId = (int) input('id', '0');
    
    if ($msgId > 0) {
        try {
            if ($action === 'read') {
                $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id")->execute(['id' => $msgId]);
                flash('contact_msg', 'Message marked as read.', 'success');
            } elseif ($action === 'unread') {
                $pdo->prepare("UPDATE contact_messages SET is_read = 0 WHERE id = :id")->execute(['id' => $msgId]);
                flash('contact_msg', 'Message marked as unread.', 'success');
            } elseif ($action === 'archive') {
                $pdo->prepare("UPDATE contact_messages SET is_archived = 1 WHERE id = :id")->execute(['id' => $msgId]);
                flash('contact_msg', 'Message archived successfully.', 'success');
            } elseif ($action === 'unarchive') {
                $pdo->prepare("UPDATE contact_messages SET is_archived = 0 WHERE id = :id")->execute(['id' => $msgId]);
                flash('contact_msg', 'Message moved back to Inbox.', 'success');
            }
        } catch (PDOException $e) {
            error_log('[admin/contact] Action failed: ' . $e->getMessage());
            flash('contact_msg', 'Failed to update contact message status.', 'error');
        }
    }
    redirect(current_url());
}

$search = trim(input('search', '', 'get'));
$filter = trim(input('filter', 'inbox', 'get')); // inbox, archived

$where = [];
$params = [];

if ($filter === 'archived') {
    $where[] = 'is_archived = 1';
} else {
    $where[] = 'is_archived = 0';
}

if (!empty($search)) {
    $where[] = '(name LIKE :search OR email LIKE :search OR subject LIKE :search OR message LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

try {
    $messages = $pdo->prepare("
        SELECT * FROM contact_messages 
        {$whereClause} 
        ORDER BY created_at DESC
    ");
    $messages->execute($params);
    $inbox = $messages->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/contact] load fail: ' . $e->getMessage());
    $inbox = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Contact Inbox</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Respond to guest queries, feedback forms, and support requests submitted on the contact storefront page.</p>
    </div>
</div>

<!-- Alert messages -->
<?php if (has_flash('contact_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('contact_msg') ?>
    </div>
<?php endif; ?>

<!-- Tabs & Search bar -->
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-4); flex-wrap:wrap; gap:12px;">
    <div style="display:inline-flex; border:1px solid var(--color-border); border-radius:var(--radius-pill); overflow:hidden; background:#fff;">
        <a href="?filter=inbox" style="padding:8px 16px; font-size:12px; text-decoration:none; font-weight:700; <?= $filter === 'inbox' ? 'background:var(--color-primary); color:#fff;' : 'color:var(--color-text-muted);' ?>">Inbox</a>
        <a href="?filter=archived" style="padding:8px 16px; font-size:12px; text-decoration:none; font-weight:700; <?= $filter === 'archived' ? 'background:var(--color-primary); color:#fff;' : 'color:var(--color-text-muted);' ?>">Archived</a>
    </div>
    
    <form method="get" style="display:flex; gap:8px;">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <input type="text" name="search" placeholder="Search keywords..." value="<?= e($search) ?>" style="padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:12px; outline:none; width:200px;">
        <button type="submit" class="btn btn-primary" style="padding:8px 14px; font-size:12px; border:none; border-radius:var(--radius-pill); font-weight:700;">Search</button>
    </form>
</div>

<!-- Messages Listing -->
<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px; width:150px;">Customer / Sender</th>
                    <th style="padding:16px 20px; width:180px;">Subject Line</th>
                    <th style="padding:16px 20px;">Message content</th>
                    <th style="padding:16px 20px; width:130px;">Received Date</th>
                    <th style="padding:16px 20px; width:120px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($inbox)): ?>
                    <?php foreach ($inbox as $msg): 
                        $read = (bool) $msg['is_read'];
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle; <?= !$read ? 'background:#e6fcf5; font-weight:600;' : '' ?>">
                            <td style="padding:12px 20px;">
                                <strong style="color:var(--color-text);"><?= e($msg['name']) ?></strong>
                                <span style="display:block; font-size:10px; color:var(--color-text-faint);"><?= e($msg['email']) ?></span>
                                <?php if (!empty($msg['phone'])): ?>
                                    <span style="display:block; font-size:9px; color:var(--color-text-faint);"><i class="fas fa-phone"></i> <?= e($msg['phone']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px 20px; color:var(--color-text);"><?= e($msg['subject']) ?></td>
                            <td style="padding:12px 20px; color:var(--color-text-muted); max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($msg['message']) ?></td>
                            <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('M d, Y H:i', strtotime($msg['created_at'])) ?></td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="reply.php?id=<?= $msg['id'] ?>" class="btn btn-primary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-reply"></i> Open</a>
                                    
                                    <!-- Read/Unread toggles -->
                                    <form method="post" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                                        <?php if ($read): ?>
                                            <input type="hidden" name="action" value="unread">
                                            <button type="submit" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); border:none;" title="Mark unread"><i class="far fa-envelope"></i></button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="read">
                                            <button type="submit" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); border:none;" title="Mark read"><i class="far fa-envelope-open"></i></button>
                                        <?php endif; ?>
                                    </form>

                                    <!-- Archive toggle -->
                                    <form method="post" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                                        <?php if ($filter === 'archived'): ?>
                                            <input type="hidden" name="action" value="unarchive">
                                            <button type="submit" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); border:none;" title="Restore to Inbox"><i class="fas fa-inbox"></i></button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="archive">
                                            <button type="submit" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); border:none;" title="Archive message"><i class="fas fa-box-archive"></i></button>
                                        <?php endif; ?>
                                    </form>

                                    <a href="delete.php?id=<?= $msg['id'] ?>" onclick="return confirm('Permanently delete this message?');" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; text-decoration:none;" title="Delete message"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="padding:32px; text-align:center; color:var(--color-text-faint);">Inbox is empty.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
