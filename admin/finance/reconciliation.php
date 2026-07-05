<?php
/**
 * ==========================================================================
 * admin/finance/reconciliation.php — Gateway Payment Reconciliation
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Payment Reconciliation — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('finance.manage');

$pdo = db();
$success = null;

// Handle manual order payment reconciliation updates
$action = input('action', '', 'get');
$orderId = (int) input('order_id', '0', 'get');

if ($orderId > 0 && ($action === 'reconcile' || $action === 'unreconcile')) {
    $status = ($action === 'reconcile') ? 'paid' : 'pending';
    try {
        $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?")->execute([$status, $orderId]);
        log_admin_activity('finance.reconcile_order', "Updated order payment status of order ID: {$orderId} to '{$status}'");
        $success = 'Order payment reconciliation state successfully updated!';
    } catch (PDOException $e) {
        error_log('[admin/finance/reconciliation] update failed: ' . $e->getMessage());
    }
}

// Fetch all online gateway paid orders (bkash, rocket, card, etc. excluding cash_on_delivery if necessary, or listing all)
try {
    $stmt = $pdo->query("
        SELECT o.id, o.order_number, o.total_amount, o.payment_method, o.payment_status, o.created_at, o.status
        FROM orders o
        WHERE o.payment_method != 'cash_on_delivery' AND o.status = 'delivered'
        ORDER BY o.created_at DESC
        LIMIT 30
    ");
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/finance/reconciliation] load failed: ' . $e->getMessage());
    $payments = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5); flex-wrap:wrap; gap:16px;">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Gateway Payment Reconciliation</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Verify gateway statements, compare payment provider values, and mark orders as paid.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> Finance Hub</a>
</div>

<!-- Alerts -->
<?php if ($success !== null): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <i class="fas fa-circle-check" style="margin-right:4px;"></i> <?= $success ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Order Number</th>
                    <th style="padding:16px 20px;">Gateway Provider</th>
                    <th style="padding:16px 20px; text-align:right; width:150px;">Order Total</th>
                    <th style="padding:16px 20px; width:180px;">Transaction Date</th>
                    <th style="padding:16px 20px; width:150px; text-align:center;">Gateway Match</th>
                    <th style="padding:16px 20px; width:180px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($payments)): ?>
                    <?php foreach ($payments as $row): 
                        $reconciled = (strtolower($row['payment_status']) === 'paid');
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong><?= e($row['order_number']) ?></strong></td>
                            <td style="padding:12px 20px; text-transform:uppercase; font-size:11px; font-weight:700; color:var(--color-primary);"><?= e($row['payment_method']) ?></td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800;">৳<?= number_format((float)$row['total_amount'], 2) ?></td>
                            <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                            <td style="padding:12px 20px; text-align:center;">
                                <span class="status-pill pill-<?= $reconciled ? 'completed' : 'pending' ?>" style="font-size:9px;">
                                    <?= $reconciled ? 'Reconciled (Paid)' : 'Unreconciled (Unpaid)' ?>
                                </span>
                            </td>
                            <td style="padding:12px 20px; text-align:right;">
                                <?php if ($reconciled): ?>
                                    <a href="?action=unreconcile&order_id=<?= $row['id'] ?>" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-rotate-left"></i> Unreconcile</a>
                                <?php else: ?>
                                    <a href="?action=reconcile&order_id=<?= $row['id'] ?>" class="btn btn-primary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); text-decoration:none;"><i class="fas fa-check"></i> Reconcile</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding:32px; text-align:center; color:var(--color-text-faint);">No gateway transaction orders recorded for reconciliation.</td>
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
