<?php
/**
 * ==========================================================================
 * admin/delivery/status.php — Update Delivery Status & Validate OTP
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Update Delivery — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('delivery.manage');

$pdo = db();
$assignId = (int) input('id', '0', 'get');

if ($assignId <= 0) {
    header('Location: index.php');
    exit;
}

$error = null;

try {
    $stmt = $pdo->prepare("
        SELECT da.*, o.order_number 
        FROM delivery_assignments da
        JOIN orders o ON o.id = da.order_id
        WHERE da.id = :id LIMIT 1
    ");
    $stmt->execute(['id' => $assignId]);
    $assign = $stmt->fetch();

    if (!$assign) {
        flash('delivery_msg', 'Assignment not found.', 'error');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('[admin/delivery/status] load failed: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $status = input('status', 'picked_up');
        $otp = trim(input('otp_code', ''));
        $failReason = trim(input('failed_reason', ''));

        if ($status === 'delivered') {
            if ($otp !== $assign['otp']) {
                $error = 'Invalid OTP verification code. Order delivery cannot be settled without a valid OTP key.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1. Update assignment
                    $pdo->prepare("UPDATE delivery_assignments SET status = 'delivered' WHERE id = ?")->execute([$assignId]);

                    // 2. Update order status to delivered
                    $pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?")->execute([$assign['order_id']]);

                    $pdo->commit();
                    log_admin_activity('delivery.status', "Delivered and verified Order ID {$assign['order_id']} via OTP validation.");
                    flash('delivery_msg', "Order '{$assign['order_number']}' successfully delivered!", 'success');
                    header('Location: index.php');
                    exit;
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('[admin/delivery/status] delivered update failed: ' . $e->getMessage());
                    $error = 'Failed to update order status to delivered.';
                }
            }
        } else {
            try {
                $pdo->beginTransaction();

                // Update assignment
                $stmtUp = $pdo->prepare("
                    UPDATE delivery_assignments 
                    SET status = ?, failed_reason = ? 
                    WHERE id = ?
                ");
                $stmtUp->execute([$status, !empty($failReason) ? $failReason : null, $assignId]);

                // If status is returned, update order status to returned/cancelled
                if ($status === 'returned') {
                    $pdo->prepare("UPDATE orders SET status = 'returned' WHERE id = ?")->execute([$assign['order_id']]);
                }

                $pdo->commit();
                log_admin_activity('delivery.status', "Updated delivery status of Order ID {$assign['order_id']} to '{$status}'");
                flash('delivery_msg', "Delivery status updated to '{$status}' successfully.", 'success');
                header('Location: index.php');
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[admin/delivery/status] failed: ' . $e->getMessage());
                $error = 'Failed to update delivery assignment status.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Update Delivery Status</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Update dispatch progress for Order: <strong><?= e($assign['order_number']) ?></strong></p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Dispatch Feed</a>
</div>

<!-- Errors display -->
<?php if ($error !== null): ?>
    <div style="background:#fff5f5; border:1px solid #ffe3e3; color:#e03131; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-exclamation" style="margin-right:4px;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:var(--space-6); max-width: 600px;">
    <form method="post" class="auth-form" id="statusForm">
        <?= csrf_field() ?>

        <div class="form-field-group">
            <label style="font-weight:700;">Delivery Status *</label>
            <select name="status" id="statusSelect" required onchange="toggleInputs(this.value);" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="picked_up" <?= $assign['status'] === 'picked_up' ? 'selected' : '' ?>>Picked Up / Out for Delivery</option>
                <option value="delivered" <?= $assign['status'] === 'delivered' ? 'selected' : '' ?>>Delivered (Requires OTP)</option>
                <option value="failed" <?= $assign['status'] === 'failed' ? 'selected' : '' ?>>Delivery Failed</option>
                <option value="returned" <?= $assign['status'] === 'returned' ? 'selected' : '' ?>>Returned to Store</option>
            </select>
        </div>

        <!-- OTP code input (only shown if status is delivered) -->
        <div class="form-field-group" id="otpGroup" style="display:none;">
            <label style="font-weight:700; color:var(--color-primary);">6-Digit Verification OTP *</label>
            <input type="text" name="otp_code" placeholder="Enter customer OTP pin" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; font-family:monospace; font-weight:700;">
            <span style="font-size:10px; color:var(--color-text-faint);">Customer receives this code at dispatch. (Internal reference: <strong><?= e($assign['otp']) ?></strong>)</span>
        </div>

        <!-- Failed reason (only shown if status is failed) -->
        <div class="form-field-group" id="failGroup" style="display:none;">
            <label style="font-weight:700;">Failed Reason *</label>
            <input type="text" name="failed_reason" placeholder="E.g. Customer unavailable / Phone switched off" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; margin-top:12px; font-size:13px;"><i class="fas fa-check"></i> Update Delivery Status</button>
    </form>
</div>

<script>
function toggleInputs(val) {
    document.getElementById('otpGroup').style.display = (val === 'delivered') ? 'block' : 'none';
    document.getElementById('failGroup').style.display = (val === 'failed') ? 'block' : 'none';
}
// Initial run
toggleInputs(document.getElementById('statusSelect').value);
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
