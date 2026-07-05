<?php
/**
 * ==========================================================================
 * admin/pos/hold-orders.php — Hold and Resume POS Cart Sessions
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Held Carts — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('pos.access');

$pdo = db();
$adminId = current_admin_id();

// Handle AJAX POST to hold cart
if (method_is('post') && input('pos_action', '') === 'hold') {
    header('Content-Type: application/json');
    if (!verify_csrf()) {
        echo json_encode(['success' => false, 'error' => 'CSRF verification failed.']);
        exit;
    }

    $customerId = (int) input('customer_id', '0');
    $cartData = input('cart_data', '[]');
    $notes = trim(input('hold_notes', 'General Suspension'));

    try {
        $stmt = $pdo->prepare("
            INSERT INTO pos_hold_orders (admin_id, customer_id, cart_data, hold_notes, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$adminId, $customerId > 0 ? $customerId : null, $cartData, $notes]);
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        error_log('[admin/pos/hold-orders] failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to suspend cart.']);
        exit;
    }
}

// Handle Resume / Delete
$action = input('action', '', 'get');
$holdId = (int) input('id', '0', 'get');

if ($holdId > 0 && $action === 'delete') {
    try {
        $pdo->prepare("DELETE FROM pos_hold_orders WHERE id = ?")->execute([$holdId]);
        flash('pos_hold_msg', 'Held cart session cancelled.', 'success');
        header('Location: hold-orders.php');
        exit;
    } catch (PDOException $e) {
        error_log('[admin/pos/hold-orders] delete failed: ' . $e->getMessage());
    }
}

// Fetch active held orders
try {
    $holds = $pdo->query("
        SELECT ho.*, u.full_name AS customer_name 
        FROM pos_hold_orders ho
        LEFT JOIN users u ON u.id = ho.customer_id
        ORDER BY ho.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/pos/hold-orders] load failed: ' . $e->getMessage());
    $holds = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Suspended Carts</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Inspect temporarily held checkout sessions and resume them.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> POS Terminal</a>
</div>

<!-- Alerts -->
<?php if (has_flash('pos_hold_msg')): ?>
    <div style="background:#e6fcf5; border:1px solid #c3fae8; color:#0ca678; padding:12px; border-radius:var(--radius-sm); font-size:var(--fs-sm); font-weight:600; margin-bottom:var(--space-4);">
        <?= flash('pos_hold_msg') ?>
    </div>
<?php endif; ?>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Suspension Note / ID</th>
                    <th style="padding:16px 20px;">Customer Profile</th>
                    <th style="padding:16px 20px; width:220px;">Suspended Date</th>
                    <th style="padding:16px 20px; width:200px; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($holds)): ?>
                    <?php foreach ($holds as $row): ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;"><strong style="color:var(--color-primary);"><?= e($row['hold_notes'] ?: 'No notes') ?></strong></td>
                            <td style="padding:12px 20px; color:var(--color-text-muted);"><?= e($row['customer_name'] ?: 'Walk-in Customer') ?></td>
                            <td style="padding:12px 20px; color:var(--color-text-faint);"><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                            <td style="padding:12px 20px; text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <button type="button" onclick='resumeCartData(<?= $row['id'] ?>, <?= $row['cart_data'] ?>, <?= $row['customer_id'] ?: 0 ?>);' class="btn btn-primary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm);"><i class="fas fa-rotate-left"></i> Resume</button>
                                    <a href="?action=delete&id=<?= $row['id'] ?>" onclick="return confirm('Cancel this held cart?');" class="btn btn-secondary" style="padding:4px 8px; font-size:10px; border-radius:var(--radius-sm); background:#f03e3e; color:#fff; text-decoration:none;"><i class="fas fa-trash"></i> Cancel</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="padding:32px; text-align:center; color:var(--color-text-faint);">No suspended carts logged in the registry.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function resumeCartData(holdId, cartData, customerId) {
    if (!window.opener) {
        alert('Please resume held carts from the terminal screen window.');
        return;
    }
    
    // Inject data into parent POS window
    window.opener.cart = cartData;
    window.opener.document.getElementById('posCustomerSelect').value = customerId;
    window.opener.renderPOSCart();
    
    // Auto cancel the hold on DB
    fetch('hold-orders.php?action=delete&id=' + holdId)
    .then(() => {
        window.close();
    });
}
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
</div>
