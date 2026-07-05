<?php
/**
 * ==========================================================================
 * admin/delivery/assign.php — Assign Order to Delivery Personnel
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Assign Delivery — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('delivery.manage');

$pdo = db();
$error = null;

try {
    $boys = $pdo->query("SELECT id, name FROM delivery_boys WHERE status = 'active' ORDER BY name ASC")->fetchAll();
    
    // Fetch orders that are processing or pending and NOT assigned to a delivery boy yet
    $orders = $pdo->query("
        SELECT o.id, o.order_number, o.total_amount 
        FROM orders o
        WHERE o.status IN ('pending', 'processing')
          AND o.id NOT IN (SELECT order_id FROM delivery_assignments WHERE status NOT IN ('failed', 'returned'))
        ORDER BY o.created_at DESC
    ")->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/delivery/assign] load options failed: ' . $e->getMessage());
    $boys = $orders = [];
}

if (method_is('post')) {
    if (!verify_csrf()) {
        $error = 'Invalid security request (CSRF check failed).';
    } else {
        $boyId = (int) input('delivery_boy_id', '0');
        $orderId = (int) input('order_id', '0');
        $route = trim(input('route_details', ''));

        if ($boyId <= 0 || $orderId <= 0 || empty($route)) {
            $error = 'Delivery Boy, Order, and route details are required fields.';
        } else {
            try {
                // Fetch default commission rate
                $stmtBoy = $pdo->prepare("SELECT commission_rate FROM delivery_boys WHERE id = ?");
                $stmtBoy->execute([$boyId]);
                $commission = (float) $stmtBoy->fetchColumn();

                $otp = (string) rand(100000, 999999);

                $pdo->beginTransaction();

                // 1. Save assignment
                $stmt = $pdo->prepare("
                    INSERT INTO delivery_assignments (delivery_boy_id, order_id, status, otp, commission_amount, route_details)
                    VALUES (?, ?, 'assigned', ?, ?, ?)
                ");
                $stmt->execute([$boyId, $orderId, $otp, $commission, $route]);

                // 2. Update order status to 'processing' (or keep as is, but mark that it is out for dispatch if needed!)
                $pdo->prepare("UPDATE orders SET status = 'processing' WHERE id = ?")->execute([$orderId]);

                $pdo->commit();
                log_admin_activity('delivery.assign', "Assigned Order ID {$orderId} to Delivery Boy ID {$boyId}. OTP generated: {$otp}");
                flash('delivery_msg', 'Order successfully assigned to delivery boy!', 'success');
                header('Location: index.php');
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('[admin/delivery/assign] failed: ' . $e->getMessage());
                $error = 'Failed to assign order due to database error.';
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Assign Order to Agent</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Assign pending retail orders to active delivery boys.</p>
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
    <form method="post" class="auth-form">
        <?= csrf_field() ?>

        <div class="form-field-group">
            <label style="font-weight:700;">Select Order *</label>
            <select name="order_id" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">Select order...</option>
                <?php foreach ($orders as $o): ?>
                    <option value="<?= $o['id'] ?>"><?= e($o['order_number']) ?> (Value: ৳<?= number_format((float)$o['total_amount'], 2) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field-group">
            <label style="font-weight:700;">Select Delivery Agent *</label>
            <select name="delivery_boy_id" required style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none; background:#fff;">
                <option value="">Select delivery boy...</option>
                <?php foreach ($boys as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field-group">
            <label style="font-weight:700;">Route Details / Delivery Address *</label>
            <input type="text" name="route_details" required placeholder="E.g. Dhaka Sector 3 to House 12, Road 4" style="width:100%; padding:8px 12px; border:1px solid var(--color-border); border-radius:var(--radius-sm); font-size:var(--fs-sm); outline:none;">
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; border:none; border-radius:var(--radius-pill); font-weight:700; padding:12px; font-size:13px;"><i class="fas fa-truck-dispatch"></i> Assign and Generate OTP</button>
    </form>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
