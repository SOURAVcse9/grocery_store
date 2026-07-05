<?php
/**
 * ==========================================================================
 * admin/admins/index.php — Administrative Accounts Directory
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Admins Directory — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('admins.manage');

$pdo = db();

try {
    // Fetch all admins and join role names
    $admins = $pdo->query("
        SELECT a.*, r.name AS role_name 
        FROM admins a
        JOIN admin_roles r ON r.id = a.role_id
        ORDER BY a.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/admins/index] load failed: ' . $e->getMessage());
    $admins = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Administrative Users</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Manage backoffice staff accounts, assign operational roles, and revoke panel access.</p>
    </div>
    <a href="create.php" class="btn btn-primary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-user-plus"></i> Add Administrator</a>
</div>

<!-- Notifications -->
<?php display_flash_alerts('admin_msg'); ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px; width:60px;">Avatar</th>
                    <th style="padding:16px 20px;">Admin Details</th>
                    <th style="padding:16px 20px; width:150px;">Role</th>
                    <th style="padding:16px 20px; width:180px;">Phone</th>
                    <th style="padding:16px 20px; width:180px;">Last Login</th>
                    <th style="padding:16px 20px; width:100px;">Status</th>
                    <th style="padding:16px 20px; width:150px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($admins)): ?>
                    <?php foreach ($admins as $ad): 
                        $status = (bool) $ad['is_active'];
                        $avatarUrl = image_url($ad['avatar'], 'users');
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;">
                                <div style="width:36px; height:36px; border-radius:50%; overflow:hidden; border:1px solid var(--color-border); background:var(--color-bg);">
                                    <img src="<?= e($avatarUrl) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                            </td>
                            <td style="padding:12px 20px;">
                                <strong style="color:var(--color-text); font-size:14px;"><?= e($ad['full_name']) ?></strong><br>
                                <span style="font-size:11px; color:var(--color-text-faint);">@<?= e($ad['username']) ?> &bull; <?= e($ad['email']) ?></span>
                            </td>
                            <td style="padding:12px 20px; text-transform:uppercase; font-size:11px; font-weight:700; color:var(--color-primary);"><?= e($ad['role_name']) ?></td>
                            <td style="padding:12px 20px; color:var(--color-text-muted);"><?= e($ad['phone'] ?: 'N/A') ?></td>
                            <td style="padding:12px 20px; color:var(--color-text-faint); font-size:12px;">
                                <?= $ad['last_login_at'] ? date('M d, Y H:i', strtotime($ad['last_login_at'])) : 'Never logged in' ?>
                            </td>
                            <td style="padding:12px 20px;">
                                <span class="status-pill pill-<?= $status ? 'completed' : 'cancelled' ?>" style="font-size:9px;">
                                    <?= $status ? 'Active' : 'Disabled' ?>
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:right;">
                                <?php if ((int)$ad['id'] !== current_admin_id()): ?>
                                    <div style="display:inline-flex; gap:6px;">
                                        <a href="edit.php?id=<?= $ad['id'] ?>" class="btn btn-primary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-pen"></i> Edit</a>
                                        <a href="delete.php?id=<?= $ad['id'] ?>" onclick="return confirm('Permanently remove this administrator account?');" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; text-decoration:none;" title="Delete Admin"><i class="fas fa-trash"></i></a>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size:11px; color:var(--color-text-faint); font-weight:600;">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="padding:32px; text-align:center; color:var(--color-text-faint);">No administrative accounts found.</td>
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
