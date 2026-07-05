<?php
/**
 * ==========================================================================
 * admin/roles/index.php — Dynamic Role & Permission Access Matrix
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Role Permissions Matrix — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('roles.manage');

$pdo = db();
$error = null;
$success = null;

try {
    // Fetch all roles except Super Admin (Super Admin gets all permissions by definition)
    $roles = $pdo->query("SELECT * FROM admin_roles WHERE id != 1 ORDER BY id ASC")->fetchAll();
    
    // Fetch all available permissions
    $permissions = $pdo->query("SELECT * FROM admin_permissions ORDER BY permission_key ASC")->fetchAll();

    // Fetch active mapping matrix
    $mappingsRaw = $pdo->query("SELECT role_id, permission_id FROM admin_role_permissions")->fetchAll();
    $matrix = [];
    foreach ($mappingsRaw as $map) {
        $matrix[$map['role_id']][$map['permission_id']] = true;
    }

} catch (PDOException $e) {
    error_log('[admin/roles/index] load failed: ' . $e->getMessage());
    $roles = $permissions = $matrix = [];
}

// Handle Access Matrix Submission updates
if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $submittedMatrix = $_POST['matrix'] ?? []; // format: [role_id][permission_id] = 1

        try {
            $pdo->beginTransaction();

            // Clear existing non-SuperAdmin role permissions mappings
            $pdo->exec("DELETE FROM admin_role_permissions WHERE role_id != 1");

            // Re-insert submitted bindings
            $stmt = $pdo->prepare("INSERT INTO admin_role_permissions (role_id, permission_id) VALUES (?, ?)");
            
            foreach ($submittedMatrix as $roleId => $permIds) {
                $roleId = (int)$roleId;
                if ($roleId === 1) continue; // skip Super Admin updates
                
                if (is_array($permIds)) {
                    foreach ($permIds as $permId => $val) {
                        $stmt->execute([$roleId, (int)$permId]);
                    }
                }
            }

            $pdo->commit();
            log_admin_activity('roles.edit', 'Updated administrative roles permission matrix mappings.');
            
            // Reload matrix mappings in-memory
            $mappingsRaw = $pdo->query("SELECT role_id, permission_id FROM admin_role_permissions")->fetchAll();
            $matrix = [];
            foreach ($mappingsRaw as $map) {
                $matrix[$map['role_id']][$map['permission_id']] = true;
            }

            $success = 'Role access permissions matrix updated successfully!';
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[admin/roles/index] matrix update failed: ' . $e->getMessage());
            $error = 'Failed to save permission matrix updates due to server transaction error.';
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Access Permissions Matrix</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Configure dynamic operational scopes for administrative roles (Admin, Manager, Staff).</p>
    </div>
</div>

<!-- Notifications -->
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<form method="post">
    <?= csrf_field() ?>

    <div class="dashboard-card" style="padding:0; overflow:hidden;">
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px; text-align:left; vertical-align:middle;">
                <thead>
                    <tr>
                        <th style="padding:16px 20px;">Permission Scope / Key</th>
                        <?php foreach ($roles as $r): ?>
                            <th style="padding:16px 20px; text-align:center; width:150px; text-transform:uppercase;"><?= e($r['name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($permissions)): ?>
                        <?php foreach ($permissions as $p): ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:12px 20px;">
                                    <strong style="color:var(--color-text); display:block;"><?= e($p['permission_key']) ?></strong>
                                    <span style="font-size:10px; color:var(--color-text-faint);"><?= e($p['description'] ?: 'No description provided.') ?></span>
                                </td>
                                <?php foreach ($roles as $r): 
                                    $hasAccess = isset($matrix[$r['id']][$p['id']]);
                                ?>
                                    <td style="padding:12px 20px; text-align:center;">
                                        <input type="checkbox" name="matrix[<?= $r['id'] ?>][<?= $p['id'] ?>]" value="1" <?= $hasAccess ? 'checked' : '' ?> style="cursor:pointer; width:15px; height:15px;">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= count($roles) + 1 ?>" style="padding:32px; text-align:center; color:var(--color-text-faint);">No security permission records configured in the database.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="padding:16px; background:var(--color-bg); border-top:1px solid var(--color-border); text-align:right;">
            <button type="submit" class="btn btn-primary" style="border:none; border-radius:var(--radius-pill); font-weight:700; padding:10px 24px;"><i class="fas fa-check"></i> Save Access Matrix</button>
        </div>
    </div>
</form>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
