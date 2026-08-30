<?php
/**
 * admin/license/index.php
 * 
 * Admin Software License & Installation Management.
 * Allows authorized administrators to monitor license validity, check remote
 * authority status, view audit logs, or deactivate/switch licenses.
 */

declare(strict_types=1);

require_once __DIR__ . '/../layouts/header.php';
require_once ROOT_PATH . '/public/includes/license.php';

// Requires settings management permission
require_admin_permission('settings.manage');

$pageTitle = 'Software License — ' . site_name();
$currentDomain = get_current_domain();
$installationId = get_installation_id();

$msgSuccess = '';
$msgError = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['license_action'] ?? '';

    if ($action === 'reverify') {
        $res = verify_license_remote(true);
        if ($res['valid']) {
            $msgSuccess = 'License re-verified successfully against authority server!';
        } else {
            $msgError = 'License verification failed: ' . ($res['reason'] ?? 'Server responded with invalid status.');
        }
    } elseif ($action === 'activate_new') {
        $newKey = trim((string)($_POST['new_license_key'] ?? ''));
        $newEmail = trim((string)($_POST['new_customer_email'] ?? ''));
        if (empty($newKey)) {
            $msgError = 'Please enter a valid license key.';
        } else {
            $res = activate_license_remote($newKey, $currentDomain, $newEmail);
            if ($res['success']) {
                $msgSuccess = $res['message'];
            } else {
                $msgError = $res['message'] ?? 'Activation failed.';
            }
        }
    } elseif ($action === 'deactivate') {
        $res = deactivate_license_local();
        if ($res['success']) {
            $msgSuccess = $res['message'];
        } else {
            $msgError = $res['message'] ?? 'Deactivation failed.';
        }
    }
}

$lic = get_local_license();

// Fetch license event logs
$logsStmt = db()->query("SELECT * FROM system_license_logs ORDER BY id DESC LIMIT 15");
$logs = $logsStmt->fetchAll();
?>

<div class="admin-content-wrapper" style="padding:var(--space-5);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
        <div>
            <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">
                <i class="fas fa-shield-halved" style="color:var(--color-primary); margin-right:8px;"></i> Software License & Activation
            </h1>
            <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">
                Manage commercial software activation, cryptographic verification, and installation binding.
            </p>
        </div>
        <div>
            <?php if ($lic && !empty($lic['license_type'])): ?>
                <span style="background:rgba(76,110,245,0.15); color:#4c6ef5; font-size:12px; font-weight:700; padding:6px 14px; border-radius:var(--radius-pill); border:1px solid rgba(76,110,245,0.3); text-transform:uppercase;">
                    <i class="fas fa-certificate"></i> <?= e($lic['license_type']) ?> LICENSE
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($msgSuccess): ?>
        <div style="background:rgba(64,192,87,0.12); border:1px solid #40c057; color:#2b8a3e; padding:12px 16px; border-radius:var(--radius-sm); font-size:13px; margin-bottom:var(--space-4); display:flex; align-items:center; gap:8px;">
            <i class="fas fa-circle-check"></i>
            <span><?= e($msgSuccess) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($msgError): ?>
        <div style="background:rgba(250,82,82,0.12); border:1px solid #fa5252; color:#fa5252; padding:12px 16px; border-radius:var(--radius-sm); font-size:13px; margin-bottom:var(--space-4); display:flex; align-items:center; gap:8px;">
            <i class="fas fa-circle-exclamation"></i>
            <span><?= e($msgError) ?></span>
        </div>
    <?php endif; ?>

    <!-- Status Overview Cards -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px; margin-bottom:var(--space-5);">
        <!-- License Card -->
        <div class="dashboard-card" style="padding:24px; margin:0;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <span style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; color:var(--color-text-muted);">Status</span>
                    <div style="margin-top:4px;">
                        <?php if (!$lic): ?>
                            <span style="background:rgba(134,142,150,0.15); color:#868e96; padding:4px 12px; border-radius:var(--radius-pill); font-size:12px; font-weight:700;">UNACTIVATED</span>
                        <?php elseif ($lic['status'] === 'active'): ?>
                            <span style="background:rgba(43,138,62,0.15); color:#2b8a3e; padding:4px 12px; border-radius:var(--radius-pill); font-size:12px; font-weight:700;">
                                <i class="fas fa-check-circle"></i> ACTIVE & VERIFIED
                            </span>
                        <?php elseif ($lic['status'] === 'suspended'): ?>
                            <span style="background:rgba(230,119,0,0.15); color:#e67700; padding:4px 12px; border-radius:var(--radius-pill); font-size:12px; font-weight:700;">SUSPENDED</span>
                        <?php elseif ($lic['status'] === 'expired'): ?>
                            <span style="background:rgba(245,159,0,0.15); color:#f59f00; padding:4px 12px; border-radius:var(--radius-pill); font-size:12px; font-weight:700;">EXPIRED</span>
                        <?php else: ?>
                            <span style="background:rgba(224,49,49,0.15); color:#e03131; padding:4px 12px; border-radius:var(--radius-pill); font-size:12px; font-weight:700;">REVOKED</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="width:40px; height:40px; border-radius:8px; background:rgba(26,157,85,0.1); color:var(--color-primary); display:flex; align-items:center; justify-content:center; font-size:18px;">
                    <i class="fas fa-key"></i>
                </div>
            </div>

            <div style="font-size:13px; color:var(--color-text); margin-bottom:8px;">
                License Key: 
                <strong style="font-family:monospace;"><?= $lic ? e($lic['license_mask']) : 'None registered' ?></strong>
            </div>
            <div style="font-size:12px; color:var(--color-text-muted); margin-bottom:6px;">
                License Type: <strong style="text-transform:uppercase; color:var(--color-primary);"><?= $lic ? e($lic['license_type'] ?? 'production') : 'N/A' ?></strong>
            </div>
            <div style="font-size:12px; color:var(--color-text-muted); margin-bottom:6px;">
                Bound Domain: <strong><?= $lic ? e($lic['domain']) : 'N/A' ?></strong>
            </div>
            <div style="font-size:12px; color:var(--color-text-muted); margin-bottom:16px;">
                Expires: <strong><?= ($lic && $lic['expires_at']) ? date('M d, Y', strtotime($lic['expires_at'])) : 'Perpetual' ?></strong>
            </div>

            <div style="display:flex; gap:10px;">
                <?php if ($lic): ?>
                    <form method="post" style="flex:1;">
                        <input type="hidden" name="license_action" value="reverify">
                        <button type="submit" class="btn btn-primary" style="width:100%; padding:8px 12px; font-size:12px; font-weight:700; border-radius:var(--radius-sm); border:none; cursor:pointer;">
                            <i class="fas fa-rotate"></i> Re-verify
                        </button>
                    </form>
                    <form method="post" onsubmit="return confirm('Are you sure you want to deactivate this installation? Production access will be locked until a new license is entered.');">
                        <input type="hidden" name="license_action" value="deactivate">
                        <button type="submit" style="padding:8px 12px; font-size:12px; font-weight:700; border-radius:var(--radius-sm); border:1px solid var(--color-border); background:var(--color-surface); color:#fa5252; cursor:pointer;">
                            <i class="fas fa-power-off"></i> Deactivate
                        </button>
                    </form>
                <?php else: ?>
                    <a href="<?= url_for('activate.php') ?>" class="btn btn-primary" style="width:100%; padding:8px 12px; font-size:12px; font-weight:700; border-radius:var(--radius-sm); text-align:center; text-decoration:none;">
                        <i class="fas fa-key"></i> Activate Now
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Node Details Card -->
        <div class="dashboard-card" style="padding:24px; margin:0;">
            <span style="font-size:11px; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; color:var(--color-text-muted);">Environment Info</span>
            <div style="margin-top:14px; font-size:12px; display:flex; flex-direction:column; gap:10px;">
                <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--color-border); padding-bottom:6px;">
                    <span style="color:var(--color-text-muted);">Current Hostname:</span>
                    <strong style="color:var(--color-text);"><?= e($currentDomain) ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--color-border); padding-bottom:6px;">
                    <span style="color:var(--color-text-muted);">Installation Node ID:</span>
                    <code style="font-size:11px; color:var(--color-primary);"><?= e(substr($installationId, 0, 18)) ?>...</code>
                </div>
                <div style="display:flex; justify-content:space-between; border-bottom:1px solid var(--color-border); padding-bottom:6px;">
                    <span style="color:var(--color-text-muted);">Last Verified:</span>
                    <span style="color:var(--color-text);"><?= ($lic && $lic['last_verified_at']) ? date('M d, Y H:i', strtotime($lic['last_verified_at'])) : 'Never' ?></span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--color-text-muted);">Next Recheck:</span>
                    <span style="color:var(--color-text);"><?= ($lic && $lic['next_check_at']) ? date('M d, Y H:i', strtotime($lic['next_check_at'])) : 'Pending' ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Activation / Switch License Form -->
    <div class="dashboard-card" style="padding:24px; margin-bottom:var(--space-5);">
        <h3 style="font-size:15px; font-weight:800; color:var(--color-text); margin:0 0 8px 0;">
            <i class="fas fa-pen-to-square"></i> Activate or Switch License
        </h3>
        <p style="font-size:12px; color:var(--color-text-muted); margin:0 0 16px 0;">
            Enter a new commercial license key to associate this installation with your GroCo subscription.
        </p>

        <form method="post" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)) auto; gap:12px; align-items:flex-end;">
            <input type="hidden" name="license_action" value="activate_new">
            <div>
                <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px;">License Key *</label>
                <input type="text" 
                       name="new_license_key" 
                       placeholder="GRCO-XXXX-XXXX-XXXX-XXXX" 
                       required 
                       style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; font-family:monospace; background:var(--color-surface); color:var(--color-text); outline:none; text-transform:uppercase;">
            </div>
            <div>
                <label style="font-size:11px; font-weight:700; color:var(--color-text-muted); display:block; margin-bottom:4px;">Contact Email</label>
                <input type="email" 
                       name="new_customer_email" 
                       placeholder="admin@yourdomain.com" 
                       style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:13px; background:var(--color-surface); color:var(--color-text); outline:none;">
            </div>
            <div>
                <button type="submit" class="btn btn-primary" style="padding:8px 20px; font-size:13px; font-weight:700; border-radius:var(--radius-sm); border:none; cursor:pointer; height:37px;">
                    Apply Key
                </button>
            </div>
        </form>
    </div>

    <!-- Audit Logs -->
    <div class="dashboard-card" style="padding:24px; margin:0;">
        <h3 style="font-size:15px; font-weight:800; color:var(--color-text); margin:0 0 16px 0;">
            <i class="fas fa-clock-rotate-left"></i> Licensing Audit Trail
        </h3>
        <?php if (empty($logs)): ?>
            <p style="font-size:12px; color:var(--color-text-muted); margin:0;">No licensing events recorded yet.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:12px;">
                    <thead>
                        <tr style="border-bottom:1px solid var(--color-border); text-align:left; color:var(--color-text-muted);">
                            <th style="padding:8px;">Timestamp</th>
                            <th style="padding:8px;">Event Type</th>
                            <th style="padding:8px;">Message</th>
                            <th style="padding:8px;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:8px; white-space:nowrap; color:var(--color-text-muted);"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></td>
                                <td style="padding:8px;">
                                    <span style="font-family:monospace; font-weight:700; color:var(--color-primary);"><?= e($log['event_type']) ?></span>
                                </td>
                                <td style="padding:8px; color:var(--color-text);"><?= e($log['message']) ?></td>
                                <td style="padding:8px; color:var(--color-text-muted); font-family:monospace;"><?= e($log['ip_address'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
