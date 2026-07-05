<?php
/**
 * ==========================================================================
 * admin/pos/cashier.php — Cashier Shifts Timeline & Drawer Reports
 * ==========================================================================
 */

declare(strict_types=1);

$pageTitle = 'Cashiers Shift Timelines — GroCo Admin';
require_once __DIR__ . '/../layouts/dashboard_layout.php';
require_admin_permission('pos.cash');

$pdo = db();

try {
    $shifts = $pdo->query("
        SELECT ps.*, a.username, a.full_name
        FROM pos_shifts ps
        JOIN admins a ON a.id = ps.admin_id
        ORDER BY ps.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('[admin/pos/cashier] load failed: ' . $e->getMessage());
    $shifts = [];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:var(--space-5);">
    <div>
        <h1 style="font-size:var(--fs-xl); font-weight:800; color:var(--color-text); margin:0;">Cashier Shift Logs</h1>
        <p style="font-size:var(--fs-sm); color:var(--color-text-muted); margin:4px 0 0 0;">Trace front-counter cashier shift open/close logs and cash audits.</p>
    </div>
    <a href="index.php" class="btn btn-secondary" style="border-radius:var(--radius-pill); font-weight:700;"><i class="fas fa-arrow-left"></i> POS Terminal</a>
</div>

<div class="dashboard-card" style="padding:0; overflow:hidden;">
    <div class="admin-table-wrapper" style="border:none;">
        <table class="admin-data-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:16px 20px;">Cashier Operator</th>
                    <th style="padding:16px 20px; text-align:right;">Opening Cash</th>
                    <th style="padding:16px 20px; text-align:right;">Closing Drawer</th>
                    <th style="padding:16px 20px; text-align:right;">Counted Cash</th>
                    <th style="padding:16px 20px; text-align:center;">Discrepancy</th>
                    <th style="padding:16px 20px;">Timeline</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($shifts)): ?>
                    <?php foreach ($shifts as $row): 
                        $expected = (float)$row['closing_cash'];
                        $actual = (float)$row['actual_cash'];
                        $diff = $actual - $expected;
                        $isOpen = ($row['status'] === 'open');
                        
                        $pillClass = $isOpen ? 'pill-pending' : 'pill-completed';
                        $statusText = $isOpen ? 'OPEN' : 'CLOSED';
                    ?>
                        <tr style="border-bottom:1px solid var(--color-border); vertical-align:middle;">
                            <td style="padding:12px 20px;">
                                <strong><?= e($row['full_name']) ?></strong><br>
                                <span style="font-size:11px; color:var(--color-text-faint);">@<?= e($row['username']) ?></span>
                            </td>
                            <td style="padding:12px 20px; text-align:right; color:var(--color-text-muted);">৳<?= number_format((float)$row['opening_cash'], 2) ?></td>
                            <td style="padding:12px 20px; text-align:right; color:var(--color-text-muted);">
                                <?= $isOpen ? 'N/A' : '৳' . number_format($expected, 2) ?>
                            </td>
                            <td style="padding:12px 20px; text-align:right; font-weight:800; color:var(--color-text);">
                                <?= $isOpen ? 'N/A' : '৳' . number_format($actual, 2) ?>
                            </td>
                            <td style="padding:12px 20px; text-align:center;">
                                <?php if ($isOpen): ?>
                                    <span class="status-pill pill-pending" style="font-size:9px;">Shift Active</span>
                                <?php else: 
                                    $diffColor = ($diff === 0.0) ? '#0ca678' : '#e03131';
                                ?>
                                    <span style="font-weight:700; color:<?= $diffColor ?>;">
                                        <?= ($diff >= 0) ? '+' : '' ?>৳<?= number_format($diff, 2) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:12px 20px; font-size:11px; color:var(--color-text-faint);">
                                <strong>Open:</strong> <?= date('Y-m-d H:i', strtotime($row['start_time'])) ?><br>
                                <?php if (!$isOpen && $row['end_time']): ?>
                                    <strong>Close:</strong> <?= date('Y-m-d H:i', strtotime($row['end_time'])) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding:32px; text-align:center; color:var(--color-text-faint);">No shift log entries recorded in the database.</td>
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
