<?php
/**
 * ==========================================================================
 * admin/security/index.php — Security and IP Lockouts Dashboard
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Security Center — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('security.manage');

$pdo = db();
$error = null;
$success = null;

// Handle IP block/unblock requests
if (method_is('post')) {
    verify_csrf_or_fail();
    $action = input('sec_action', '');

    if ($action === 'block_ip') {
        $ip = trim(input('ip_address', ''));
        $reason = trim(input('reason', 'Administrative block'));

        if (empty($ip)) {
            $error = 'IP Address is a required field.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO blocked_ips (ip_address, reason) VALUES (?, ?)");
                $stmt->execute([$ip, $reason]);
                log_admin_activity('security.block_ip', "Blocked IP Address: {$ip}");
                $success = "IP Address '{$ip}' blocked successfully.";
            } catch (PDOException $e) {
                $error = "Failed to block IP Address: might be already blocked.";
            }
        }
    } elseif ($action === 'unblock_ip') {
        $blockId = (int) input('block_id', '0');
        if ($blockId > 0) {
            try {
                $stmtIp = $pdo->prepare("SELECT ip_address FROM blocked_ips WHERE id = ?");
                $stmtIp->execute([$blockId]);
                $ip = $stmtIp->fetchColumn();

                if ($ip) {
                    $pdo->prepare("DELETE FROM blocked_ips WHERE id = ?")->execute([$blockId]);
                    log_admin_activity('security.unblock_ip', "Unblocked IP Address: {$ip}");
                    $success = "IP Address '{$ip}' unblocked successfully.";
                }
            } catch (PDOException $e) {
                $error = 'Failed to unblock IP address.';
            }
        }
    }
}

// Fetch blocked IPs
try {
    $blockedList = $pdo->query("SELECT * FROM blocked_ips ORDER BY blocked_at DESC")->fetchAll();
    
    // Fetch failed login attempts from admin_login_logs
    $failedAttempts = $pdo->query("
        SELECT username, ip_address, login_time, success, user_agent 
        FROM admin_login_logs 
        WHERE success = 0 
        ORDER BY login_time DESC 
        LIMIT 10
    ")->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/security] load failed: ' . $e->getMessage());
    $blockedList = $failedAttempts = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Security Center</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect failed log in attempts, moderate access blacklist rules, and block malicious traffic.</p>
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

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:var(--space-6);" class="admin-dashboard-layout">
    
    <!-- Left Column: Block IPs list & Add IP block -->
    <div style="display:flex; flex-direction:column; gap:var(--space-4);">
        <!-- Block an IP form -->
        <div class="dashboard-card" style="margin:0; padding:var(--space-5);">
            <h3 style="font-size:14px; font-weight:800; border-bottom:1px solid var(--color-border); padding-bottom:6px; margin:0 0 16px 0;">Blacklist IP Address</h3>
            <form method="post" class="auth-form" style="display:flex; gap:10px; align-items:end;">
                <?= csrf_field() ?>
                <input type="hidden" name="sec_action" value="block_ip">
                
                <div class="form-field-group" style="margin:0; flex:1;">
                    <label style="font-weight:700;">IP Address *</label>
                    <input type="text" name="ip_address" required placeholder="E.g. 192.168.1.100" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <div class="form-field-group" style="margin:0; flex:1.2;">
                    <label style="font-weight:700;">Reason *</label>
                    <input type="text" name="reason" required placeholder="Brute-force attempts" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
                </div>
                <button type="submit" class="btn btn-primary" style="padding:9px 18px; border:none; border-radius:var(--radius-pill); font-weight:700;">Block</button>
            </form>
        </div>

        <!-- Blocked IPs directory -->
        <div class="dashboard-card" style="margin:0; padding:0; overflow:hidden;">
            <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
                <h3 style="font-size:14px; font-weight:800; margin:0;">Blacklisted IP Registry</h3>
            </div>
            <div class="admin-table-wrapper" style="border:none;">
                <table class="admin-data-table" style="font-size:12px;">
                    <thead>
                        <tr>
                            <th style="padding:10px 15px;">IP Address</th>
                            <th style="padding:10px 15px;">Reason</th>
                            <th style="padding:10px 15px; text-align:right; width:80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($blockedList)): ?>
                            <?php foreach ($blockedList as $row): ?>
                                <tr style="border-bottom:1px solid var(--color-border);">
                                    <td style="padding:8px 15px;"><strong><?= e($row['ip_address']) ?></strong></td>
                                    <td style="padding:8px 15px; color:var(--color-text-muted);"><?= e($row['reason']) ?></td>
                                    <td style="padding:8px 15px; text-align:right;">
                                        <form method="post" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sec_action" value="unblock_ip">
                                            <input type="hidden" name="block_id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-secondary" onclick="return confirm('Remove IP block?');" style="padding:4px 8px; font-size:9px; border-radius:var(--radius-sm); background:#0ca678; color:#fff; border:none; cursor:pointer;">Unblock</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="padding:20px; text-align:center; color:var(--color-text-faint);">No blacklisted IP addresses.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Failed attempts -->
    <div class="dashboard-card" style="margin:0; padding:0; overflow:hidden;">
        <div style="padding:16px; border-bottom:1px solid var(--color-border); background:var(--color-bg);">
            <h3 style="font-size:14px; font-weight:800; margin:0;"><i class="fas fa-user-shield"></i> Failed Log In Attempts (Last 10)</h3>
        </div>
        <div class="admin-table-wrapper" style="border:none;">
            <table class="admin-data-table" style="font-size:12px;">
                <thead>
                    <tr>
                        <th style="padding:10px 15px;">Attempt Username</th>
                        <th style="padding:10px 15px;">IP Address</th>
                        <th style="padding:10px 15px;">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($failedAttempts)): ?>
                        <?php foreach ($failedAttempts as $row): ?>
                            <tr style="border-bottom:1px solid var(--color-border);">
                                <td style="padding:8px 15px;"><strong style="color:#e03131;">@<?= e($row['username']) ?></strong></td>
                                <td style="padding:8px 15px; font-family:monospace;"><?= e($row['ip_address']) ?></td>
                                <td style="padding:8px 15px; color:var(--color-text-faint);"><?= date('M d, H:i', strtotime($row['login_time'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="padding:20px; text-align:center; color:var(--color-text-faint);">No failed login attempts recorded.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
