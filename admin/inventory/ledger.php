<?php
/**
 * ==========================================================================
 * admin/inventory/ledger.php — Stock Movement History Timeline
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Stock Movements — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('inventory.manage');

$pdo = db();
$productId = (int) input('id', '0', 'get');

if ($productId <= 0) {
    header('Location: index.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $productId]);
    $name = $stmt->fetchColumn();

    if (!$name) {
        flash('inventory_msg', 'Product not found.', 'error');
        header('Location: index.php');
        exit;
    }

    // Fetch chronological inventory movements
    $stmtLogs = $pdo->prepare("
        SELECT il.*, a.username 
        FROM inventory_logs il
        LEFT JOIN admins a ON a.id = il.admin_id
        WHERE il.product_id = :pid
        ORDER BY il.created_at DESC
    ");
    $stmtLogs->execute(['pid' => $productId]);
    $logs = $stmtLogs->fetchAll();

} catch (PDOException $e) {
    error_log('[admin/inventory/ledger] load failed: ' . $e->getMessage());
    header('Location: index.php');
    exit;
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Stock Movement Timeline</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Product: <strong><?= e($name) ?></strong> (ID: #<?= $productId ?>)</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700; padding:10px 20px;"><i class="fas fa-arrow-left"></i> Inventory Ledger</a>
</div>

<div class="dashboard-card" style="padding:var(--space-5);">
    <?php if (!empty($logs)): ?>
        <div style="display:flex; flex-direction:column; gap:16px; position:relative; padding-left:20px; border-left:2px solid var(--color-border);">
            <?php foreach ($logs as $row): 
                $type = strtolower($row['type']);
                $qty = (int)$row['quantity'];
                
                $color = '#339af0';
                $sign = '';
                if ($type === 'stock_in' || $qty > 0) {
                    $color = '#0ca678';
                    $sign = '+';
                } elseif ($type === 'stock_out' || $qty < 0) {
                    $color = '#f03e3e';
                }
            ?>
                <div style="position:relative; margin-bottom:4px;">
                    <div style="position:absolute; left:-27px; top:4px; width:12px; height:12px; border-radius:50%; background:<?= $color ?>; border:2px solid #fff;"></div>
                    
                    <span style="font-size:10px; color:var(--color-text-faint); font-weight:700; text-transform:uppercase; display:block;"><?= date('M d, Y H:i:s', strtotime($row['created_at'])) ?></span>
                    <strong style="color:var(--color-text); font-size:13px; text-transform:uppercase;"><?= e(str_replace('_', ' ', $row['type'])) ?></strong> 
                    <span style="color:<?= $color ?>; font-weight:800; font-size:14px; margin-left:8px;"><?= $sign ?><?= $qty ?> UNITS</span>
                    <p style="margin:2px 0 0 0; font-size:12px; color:var(--color-text-muted);"><?= e($row['note']) ?></p>
                    <span style="font-size:10px; color:var(--color-text-faint); display:block; margin-top:2px;">Operator: <strong><?= $row['username'] ? '@' . e($row['username']) : 'System' ?></strong> | Remaining Stock: <strong><?= $row['remaining_stock'] ?> units</strong></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="text-align:center; color:var(--color-text-faint); padding:32px; margin:0;">No inventory logs or stock movements recorded for this item.</p>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
